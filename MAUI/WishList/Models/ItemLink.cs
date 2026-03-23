using System.Text.Json.Serialization;

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
}
