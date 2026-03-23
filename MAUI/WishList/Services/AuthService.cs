using System.Net.Http.Json;
using System.Text.Json;
using WishList.Helpers;
using WishList.Models;

namespace WishList.Services;

public class AuthService : IAuthService
{
    private readonly HttpClient _httpClient;
    private User? _currentUser;

    public User? CurrentUser => _currentUser;
    public bool IsAuthenticated => _currentUser != null;
    public event Action? AuthStateChanged;

    public AuthService()
    {
        _httpClient = new HttpClient
        {
            BaseAddress = new Uri(Constants.ApiBaseUrl)
        };
    }

    public async Task<bool> TryRestoreSessionAsync()
    {
        var token = await SecureStorageHelper.GetTokenAsync();
        if (string.IsNullOrEmpty(token))
            return false;

        SetAuthHeader(token);

        try
        {
            _currentUser = await GetMeAsync();
            AuthStateChanged?.Invoke();
            return true;
        }
        catch
        {
            SecureStorageHelper.RemoveToken();
            _httpClient.DefaultRequestHeaders.Authorization = null;
            return false;
        }
    }

    public async Task<User> LoginAsync(string email, string password)
    {
        var response = await _httpClient.PostAsJsonAsync("/api/auth/login", new { email, password });
        await EnsureSuccessAsync(response);

        var result = await response.Content.ReadFromJsonAsync<JsonElement>();
        var token = result.GetProperty("token").GetString()!;
        var user = result.GetProperty("user").Deserialize<User>()!;

        await SecureStorageHelper.SetTokenAsync(token);
        SetAuthHeader(token);
        _currentUser = user;
        AuthStateChanged?.Invoke();
        return user;
    }

    public async Task<User> RegisterAsync(string email, string password, string displayName)
    {
        var response = await _httpClient.PostAsJsonAsync("/api/auth/register", new { email, password, display_name = displayName });
        await EnsureSuccessAsync(response);

        var result = await response.Content.ReadFromJsonAsync<JsonElement>();
        var token = result.GetProperty("token").GetString()!;
        var user = result.GetProperty("user").Deserialize<User>()!;

        await SecureStorageHelper.SetTokenAsync(token);
        SetAuthHeader(token);
        _currentUser = user;
        AuthStateChanged?.Invoke();
        return user;
    }

    public async Task<User> GetMeAsync()
    {
        var response = await _httpClient.GetAsync("/api/auth/me");
        await EnsureSuccessAsync(response);

        var result = await response.Content.ReadFromJsonAsync<JsonElement>();
        return result.GetProperty("user").Deserialize<User>()!;
    }

    public void Logout()
    {
        SecureStorageHelper.RemoveToken();
        _httpClient.DefaultRequestHeaders.Authorization = null;
        _currentUser = null;
        AuthStateChanged?.Invoke();
    }

    private void SetAuthHeader(string token)
    {
        _httpClient.DefaultRequestHeaders.Authorization =
            new System.Net.Http.Headers.AuthenticationHeaderValue("Bearer", token);

        // Fallback header for hosts that strip Authorization
        if (_httpClient.DefaultRequestHeaders.Contains("X-Auth-Token"))
            _httpClient.DefaultRequestHeaders.Remove("X-Auth-Token");
        _httpClient.DefaultRequestHeaders.Add("X-Auth-Token", token);
    }

    private static async Task EnsureSuccessAsync(HttpResponseMessage response)
    {
        if (!response.IsSuccessStatusCode)
        {
            var body = await response.Content.ReadAsStringAsync();
            try
            {
                var error = JsonSerializer.Deserialize<JsonElement>(body);
                var message = error.TryGetProperty("message", out var msg)
                    ? msg.GetString()
                    : error.TryGetProperty("error", out var err)
                        ? err.GetString()
                        : $"Request failed with status {(int)response.StatusCode}";
                throw new ApiException(message ?? "Request failed", (int)response.StatusCode);
            }
            catch (JsonException)
            {
                throw new ApiException($"Request failed with status {(int)response.StatusCode}", (int)response.StatusCode);
            }
        }
    }
}

public class ApiException : Exception
{
    public int StatusCode { get; }

    public ApiException(string message, int statusCode) : base(message)
    {
        StatusCode = statusCode;
    }
}
