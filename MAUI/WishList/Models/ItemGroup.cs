using System.Text.Json.Serialization;

namespace WishList.Models;

/// <summary>A titled section of a wish list that items can be dragged into.</summary>
public class ItemGroup
{
    [JsonPropertyName("id")]
    [JsonNumberHandling(JsonNumberHandling.AllowReadingFromString)]
    public int Id { get; set; }

    [JsonPropertyName("list_id")]
    [JsonNumberHandling(JsonNumberHandling.AllowReadingFromString)]
    public int ListId { get; set; }

    [JsonPropertyName("title")]
    public string Title { get; set; } = string.Empty;

    [JsonPropertyName("description")]
    public string? Description { get; set; }

    [JsonPropertyName("sort_order")]
    [JsonNumberHandling(JsonNumberHandling.AllowReadingFromString)]
    public int SortOrder { get; set; }
}
