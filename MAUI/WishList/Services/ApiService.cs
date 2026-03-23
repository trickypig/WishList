using System.Net.Http.Json;
using System.Text.Json;
using WishList.Helpers;
using WishList.Models;

namespace WishList.Services;

public class ApiService : IApiService
{
    private readonly HttpClient _httpClient;
    private readonly IAuthService _authService;

    public ApiService(IAuthService authService)
    {
        _authService = authService;
        _httpClient = new HttpClient
        {
            BaseAddress = new Uri(Constants.ApiBaseUrl)
        };
    }

    private async Task SetAuthHeaderAsync()
    {
        var token = await SecureStorageHelper.GetTokenAsync();
        if (!string.IsNullOrEmpty(token))
        {
            _httpClient.DefaultRequestHeaders.Authorization =
                new System.Net.Http.Headers.AuthenticationHeaderValue("Bearer", token);

            // Fallback header for hosts that strip Authorization
            if (_httpClient.DefaultRequestHeaders.Contains("X-Auth-Token"))
                _httpClient.DefaultRequestHeaders.Remove("X-Auth-Token");
            _httpClient.DefaultRequestHeaders.Add("X-Auth-Token", token);
        }
    }

    private async Task<T> GetAsync<T>(string path)
    {
        await SetAuthHeaderAsync();
        var response = await _httpClient.GetAsync($"/api{path}");
        await EnsureSuccessAsync(response);
        return (await response.Content.ReadFromJsonAsync<T>())!;
    }

    private async Task<T> PostAsync<T>(string path, object? body = null)
    {
        await SetAuthHeaderAsync();
        var response = await _httpClient.PostAsJsonAsync($"/api{path}", body);
        await EnsureSuccessAsync(response);
        return (await response.Content.ReadFromJsonAsync<T>())!;
    }

    private async Task PostAsync(string path, object? body = null)
    {
        await SetAuthHeaderAsync();
        var response = await _httpClient.PostAsJsonAsync($"/api{path}", body);
        await EnsureSuccessAsync(response);
    }

    private async Task<T> PutAsync<T>(string path, object? body = null)
    {
        await SetAuthHeaderAsync();
        var response = await _httpClient.PutAsJsonAsync($"/api{path}", body);
        await EnsureSuccessAsync(response);
        return (await response.Content.ReadFromJsonAsync<T>())!;
    }

    private async Task PutAsync(string path, object? body = null)
    {
        await SetAuthHeaderAsync();
        var response = await _httpClient.PutAsJsonAsync($"/api{path}", body);
        await EnsureSuccessAsync(response);
    }

    private async Task DeleteAsync(string path)
    {
        await SetAuthHeaderAsync();
        var response = await _httpClient.DeleteAsync($"/api{path}");
        await EnsureSuccessAsync(response);
    }

    // Lists
    public async Task<List<WishListModel>> GetListsAsync()
    {
        var result = await GetAsync<JsonElement>("/lists");
        return result.GetProperty("lists").Deserialize<List<WishListModel>>() ?? [];
    }

    public async Task<WishListModel> CreateListAsync(string title, string description, string visibility)
    {
        var result = await PostAsync<JsonElement>("/lists", new { title, description, visibility });
        return result.GetProperty("list").Deserialize<WishListModel>()!;
    }

    public async Task<WishListModel> GetListAsync(int id)
    {
        var result = await GetAsync<JsonElement>($"/lists/{id}");
        return result.GetProperty("list").Deserialize<WishListModel>()!;
    }

    public async Task<WishListModel> UpdateListAsync(int id, string title, string description)
    {
        var result = await PutAsync<JsonElement>($"/lists/{id}", new { title, description });
        return result.GetProperty("list").Deserialize<WishListModel>()!;
    }

    public async Task DeleteListAsync(int id)
    {
        await DeleteAsync($"/lists/{id}");
    }

    public async Task<WishListModel> UpdateListSharingAsync(int id, string visibility, List<int> familyIds)
    {
        var result = await PutAsync<JsonElement>($"/lists/{id}/sharing", new { visibility, family_ids = familyIds });
        return result.GetProperty("list").Deserialize<WishListModel>()!;
    }

    public async Task<List<WishListModel>> GetFamilyListsAsync(int familyId)
    {
        var result = await GetAsync<JsonElement>($"/lists/family/{familyId}");
        return result.GetProperty("lists").Deserialize<List<WishListModel>>() ?? [];
    }

    // Items
    public async Task<Item> CreateItemAsync(int listId, Item item)
    {
        var result = await PostAsync<JsonElement>($"/lists/{listId}/items", new
        {
            name = item.Name,
            description = item.Description,
            price = item.Price,
            quantity_desired = item.QuantityDesired,
            image_url = item.ImageUrl,
            sort_order = item.SortOrder,
            links = item.Links.Select(l => new { url = l.Url, store_name = l.StoreName }).ToList()
        });
        return result.GetProperty("item").Deserialize<Item>()!;
    }

    public async Task<Item> UpdateItemAsync(int id, Item item)
    {
        var result = await PutAsync<JsonElement>($"/items/{id}", new
        {
            name = item.Name,
            description = item.Description,
            price = item.Price,
            quantity_desired = item.QuantityDesired,
            image_url = item.ImageUrl,
            sort_order = item.SortOrder,
            links = item.Links.Select(l => new { url = l.Url, store_name = l.StoreName }).ToList()
        });
        return result.GetProperty("item").Deserialize<Item>()!;
    }

    public async Task DeleteItemAsync(int id)
    {
        await DeleteAsync($"/items/{id}");
    }

    public async Task ReorderItemsAsync(int listId, List<int> itemIds)
    {
        await PutAsync($"/lists/{listId}/items/reorder", new { item_ids = itemIds });
    }

    // Purchases
    public async Task<Purchase> PurchaseItemAsync(int itemId, int quantity)
    {
        var result = await PostAsync<JsonElement>($"/items/{itemId}/purchase", new { quantity });
        return result.GetProperty("purchase").Deserialize<Purchase>()!;
    }

    public async Task UnpurchaseItemAsync(int itemId)
    {
        await DeleteAsync($"/items/{itemId}/purchase");
    }

    // Families
    public async Task<List<Family>> GetFamiliesAsync()
    {
        var result = await GetAsync<JsonElement>("/families");
        return result.GetProperty("families").Deserialize<List<Family>>() ?? [];
    }

    public async Task<Family> CreateFamilyAsync(string name)
    {
        var result = await PostAsync<JsonElement>("/families", new { name });
        return result.GetProperty("family").Deserialize<Family>()!;
    }

    public async Task<Family> GetFamilyAsync(int id)
    {
        var result = await GetAsync<JsonElement>($"/families/{id}");
        return result.GetProperty("family").Deserialize<Family>()!;
    }

    public async Task<Family> JoinFamilyAsync(string inviteCode)
    {
        var result = await PostAsync<JsonElement>("/families/join", new { invite_code = inviteCode });
        return result.GetProperty("family").Deserialize<Family>()!;
    }

    public async Task InviteToFamilyAsync(int familyId, string email)
    {
        await PostAsync($"/families/{familyId}/invite", new { email });
    }

    public async Task RemoveFamilyMemberAsync(int familyId, int userId)
    {
        await DeleteAsync($"/families/{familyId}/members/{userId}");
    }

    public async Task<User> SetChildStatusAsync(int familyId, int userId, int isChild)
    {
        var result = await PutAsync<JsonElement>($"/families/{familyId}/members/{userId}/child-status", new { is_child = isChild });
        return result.GetProperty("user").Deserialize<User>()!;
    }

    // Domains
    public async Task<List<AllowedDomain>> GetFamilyDomainsAsync(int familyId)
    {
        var result = await GetAsync<JsonElement>($"/families/{familyId}/domains");
        return result.GetProperty("domains").Deserialize<List<AllowedDomain>>() ?? [];
    }

    public async Task AddFamilyDomainAsync(int familyId, string domain, string displayName)
    {
        await PostAsync($"/families/{familyId}/domains", new { domain, display_name = displayName });
    }

    public async Task RemoveFamilyDomainAsync(int familyId, string domain)
    {
        await DeleteAsync($"/families/{familyId}/domains/{domain}");
    }

    // Scraping
    public async Task<ScrapeResult> ScrapeUrlAsync(string url)
    {
        var result = await PostAsync<ScrapeResult>("/scrape/url", new { url });
        return result;
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
