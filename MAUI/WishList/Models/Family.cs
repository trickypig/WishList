using System.Text.Json.Serialization;

namespace WishList.Models;

public class Family
{
    [JsonPropertyName("id")]
    public int Id { get; set; }

    [JsonPropertyName("name")]
    public string Name { get; set; } = string.Empty;

    [JsonPropertyName("invite_code")]
    public string InviteCode { get; set; } = string.Empty;

    [JsonPropertyName("role")]
    public string? Role { get; set; }

    [JsonPropertyName("my_role")]
    public string? MyRole { get; set; }

    [JsonPropertyName("member_count")]
    public int? MemberCount { get; set; }

    [JsonPropertyName("created_by")]
    public int CreatedBy { get; set; }

    [JsonPropertyName("members")]
    public List<FamilyMember>? Members { get; set; }

    public string EffectiveRole => Role ?? MyRole ?? "member";
}
