using System.Text.Json.Serialization;

namespace WishList.Models;

public class FamilyMember
{
    [JsonPropertyName("id")]
    public int Id { get; set; }

    [JsonPropertyName("user_id")]
    public int UserId { get; set; }

    [JsonPropertyName("display_name")]
    public string DisplayName { get; set; } = string.Empty;

    [JsonPropertyName("email")]
    public string Email { get; set; } = string.Empty;

    [JsonPropertyName("role")]
    public string Role { get; set; } = "member";

    [JsonPropertyName("is_child")]
    public int IsChild { get; set; }
}
