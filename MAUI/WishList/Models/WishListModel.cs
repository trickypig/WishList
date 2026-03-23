using System.Text.Json.Serialization;

namespace WishList.Models;

public class WishListModel
{
    [JsonPropertyName("id")]
    public int Id { get; set; }

    [JsonPropertyName("user_id")]
    public int UserId { get; set; }

    [JsonPropertyName("title")]
    public string Title { get; set; } = string.Empty;

    [JsonPropertyName("description")]
    public string Description { get; set; } = string.Empty;

    [JsonPropertyName("visibility")]
    public string Visibility { get; set; } = "all_families";

    [JsonPropertyName("owner_name")]
    public string? OwnerName { get; set; }

    [JsonPropertyName("created_at")]
    public string CreatedAt { get; set; } = string.Empty;

    [JsonPropertyName("items")]
    public List<Item>? Items { get; set; }

    [JsonPropertyName("family_ids")]
    public List<int>? FamilyIds { get; set; }

    [JsonPropertyName("item_count")]
    public int? ItemCount { get; set; }
}
