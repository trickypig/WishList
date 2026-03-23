using System.Text.Json.Serialization;

namespace WishList.Models;

public class Item
{
    [JsonPropertyName("id")]
    public int Id { get; set; }

    [JsonPropertyName("list_id")]
    public int ListId { get; set; }

    [JsonPropertyName("name")]
    public string Name { get; set; } = string.Empty;

    [JsonPropertyName("description")]
    public string Description { get; set; } = string.Empty;

    [JsonPropertyName("price")]
    [JsonNumberHandling(JsonNumberHandling.AllowReadingFromString)]
    public decimal? Price { get; set; }

    [JsonPropertyName("quantity_desired")]
    public int QuantityDesired { get; set; } = 1;

    [JsonPropertyName("image_url")]
    public string ImageUrl { get; set; } = string.Empty;

    [JsonPropertyName("sort_order")]
    public int SortOrder { get; set; }

    [JsonPropertyName("links")]
    public List<ItemLink> Links { get; set; } = [];

    [JsonPropertyName("total_purchased")]
    public int? TotalPurchased { get; set; }

    [JsonPropertyName("purchased_by_me")]
    public int? PurchasedByMe { get; set; }
}
