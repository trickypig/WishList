using System.Net;
using System.Net.Http.Headers;
using System.Net.Http.Json;
using System.Text.Json;
using WishList.Helpers;
using WishList.Models;

namespace WishList.Share;

/// <summary>The saved login is missing, expired or rejected: the user has to open the app and log in.</summary>
public sealed class NotLoggedInException : Exception
{
    public NotLoggedInException() : base("Open Wish List and log in first") { }
}

/// <summary>
/// The few API calls the share extension makes. ApiService depends on MAUI storage and services,
/// which an iOS extension doesn't have, so this mirrors its paths and JSON with a plain HttpClient.
/// </summary>
public sealed class ShareApiClient
{
    readonly HttpClient _http;

    public ShareApiClient(string token, HttpMessageHandler? handler = null)
    {
        _http = handler is null ? new HttpClient() : new HttpClient(handler);
        _http.BaseAddress = new Uri(Constants.ApiBaseUrl);
        _http.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", token);
        // Fallback header for hosts that strip Authorization (same as ApiService)
        _http.DefaultRequestHeaders.Add("X-Auth-Token", token);
    }

    public async Task<List<WishListModel>> GetListsAsync(CancellationToken ct = default)
    {
        var result = await SendAsync(() => _http.GetAsync("/api/lists", ct), ct);
        return result.GetProperty("lists").Deserialize<List<WishListModel>>() ?? [];
    }

    public async Task<ScrapeResult> ScrapeUrlAsync(string url, CancellationToken ct = default)
    {
        var result = await SendAsync(() => _http.PostAsJsonAsync("/api/scrape/url", new { url }, ct), ct);
        return result.Deserialize<ScrapeResult>()!;
    }

    public async Task<ScrapeResult> ScrapeHtmlAsync(string url, string html, CancellationToken ct = default)
    {
        var result = await SendAsync(() => _http.PostAsJsonAsync("/api/scrape/html", new { url, html }, ct), ct);
        return result.Deserialize<ScrapeResult>()!;
    }

    public async Task<Item> CreateItemAsync(int listId, Item item, CancellationToken ct = default)
    {
        var body = new
        {
            name = item.Name,
            description = item.Description,
            price = item.Price,
            quantity_desired = item.QuantityDesired,
            image_url = item.ImageUrl,
            sort_order = item.SortOrder,
            links = item.Links.Select(l => new { url = l.Url, store_name = l.StoreName }).ToList()
        };
        var result = await SendAsync(() => _http.PostAsJsonAsync($"/api/lists/{listId}/items", body, ct), ct);
        return result.GetProperty("item").Deserialize<Item>()!;
    }

    static async Task<JsonElement> SendAsync(Func<Task<HttpResponseMessage>> send, CancellationToken ct)
    {
        using var response = await send();
        if (response.StatusCode == HttpStatusCode.Unauthorized)
            throw new NotLoggedInException();

        var text = await response.Content.ReadAsStringAsync(ct);
        if (!response.IsSuccessStatusCode)
            throw new HttpRequestException(ErrorMessage(text) ?? $"Request failed with status {(int)response.StatusCode}");

        return JsonSerializer.Deserialize<JsonElement>(text);
    }

    static string? ErrorMessage(string body)
    {
        try
        {
            var error = JsonSerializer.Deserialize<JsonElement>(body);
            if (error.ValueKind != JsonValueKind.Object) return null;
            if (error.TryGetProperty("message", out var msg) && msg.ValueKind == JsonValueKind.String) return msg.GetString();
            if (error.TryGetProperty("error", out var err) && err.ValueKind == JsonValueKind.String) return err.GetString();
        }
        catch (JsonException) { }
        return null;
    }
}
