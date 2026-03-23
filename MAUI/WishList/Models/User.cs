using System.Text.Json.Serialization;

namespace WishList.Models;

public class User
{
    [JsonPropertyName("id")]
    public int Id { get; set; }

    [JsonPropertyName("email")]
    public string Email { get; set; } = string.Empty;

    [JsonPropertyName("display_name")]
    public string DisplayName { get; set; } = string.Empty;

    [JsonPropertyName("is_admin")]
    public int IsAdmin { get; set; }

    [JsonPropertyName("is_child")]
    public int IsChild { get; set; }
}
