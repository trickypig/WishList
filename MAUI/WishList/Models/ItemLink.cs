using System.Text.Json.Serialization;
using WishList.Helpers;

namespace WishList.Models;

public class ItemLink
{
    [JsonPropertyName("id")]
    public int? Id { get; set; }

    [JsonPropertyName("item_id")]
    public int? ItemId { get; set; }

    [JsonPropertyName("url")]
    public string Url { get; set; } = string.Empty;

    [JsonPropertyName("store_name")]
    public string StoreName { get; set; } = string.Empty;

    /// <summary>What the link button says: the store's name, or where the link goes.</summary>
    [JsonIgnore]
    public string Label => string.IsNullOrWhiteSpace(StoreName) ? AllowedSites.HostOf(Url) : StoreName.Trim();

    /// <summary>Only web pages open in the site viewer.</summary>
    [JsonIgnore]
    public bool CanOpen => AllowedSites.IsWebUrl(Url);
}
