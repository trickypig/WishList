using System.Text.Json.Serialization;

namespace WishList.Models;

public class Purchase
{
    [JsonPropertyName("id")]
    public int Id { get; set; }

    [JsonPropertyName("item_id")]
    public int ItemId { get; set; }

    [JsonPropertyName("user_id")]
    public int UserId { get; set; }

    [JsonPropertyName("quantity")]
    public int Quantity { get; set; }

    [JsonPropertyName("purchased_at")]
    public string PurchasedAt { get; set; } = string.Empty;
}
