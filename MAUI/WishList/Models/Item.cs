using System.ComponentModel;
using System.Runtime.CompilerServices;
using System.Text.Json.Serialization;
using WishList.Helpers;

namespace WishList.Models;

public class Item : INotifyPropertyChanged
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

    /// <summary>The group this item sits in, or null when it is in the ungrouped section.</summary>
    [JsonPropertyName("group_id")]
    [JsonNumberHandling(JsonNumberHandling.AllowReadingFromString)]
    public int? GroupId { get; set; }

    [JsonPropertyName("suggested_by")]
    [JsonNumberHandling(JsonNumberHandling.AllowReadingFromString)]
    public int? SuggestedBy { get; set; }

    [JsonPropertyName("suggested_by_name")]
    public string? SuggestedByName { get; set; }

    [JsonIgnore]
    public bool IsSuggestion => SuggestedBy != null;

    [JsonIgnore]
    public string SuggestedByLabel => $"Suggested by {SuggestedByName ?? "Family member"}";

    [JsonIgnore]
    public bool CanEdit { get; set; }

    [JsonIgnore]
    public bool CanDelete { get; set; }

    [JsonIgnore]
    public bool CanReorder { get; set; }

    [JsonIgnore]
    public bool CanPurchase { get; set; }

    [JsonIgnore]
    public bool HasMyPurchase => PurchasedByMe > 0;

    /// <summary>Items inside a group are drawn a little smaller.</summary>
    [JsonIgnore]
    public bool IsInGroup => GroupId != null;

    [JsonPropertyName("links")]
    public List<ItemLink> Links { get; set; } = [];

    [JsonPropertyName("total_purchased")]
    public int? TotalPurchased { get; set; }

    [JsonPropertyName("purchased_by_me")]
    public int? PurchasedByMe { get; set; }

    /// <summary>Whether to show the "(X purchased)" label.</summary>
    [JsonIgnore]
    public bool HasPurchases => TotalPurchased > 0;

    /// <summary>Whether others have bought every one wanted. Always false for the list owner, who gets no purchase counts.</summary>
    [JsonIgnore]
    public bool IsFullyPurchased => TotalPurchased >= QuantityDesired;

    // ===== Bold Pop tile view state (never sent to or read from the API) =====

    public event PropertyChangedEventHandler? PropertyChanged;

    void Raise([CallerMemberName] string? name = null) => PropertyChanged?.Invoke(this, new PropertyChangedEventArgs(name));

    /// <summary>Position in the list; picks the tile color.</summary>
    [JsonIgnore]
    public int TileIndex { get; set; }

    [JsonIgnore]
    public string TileHex => BoldPop.TileHexAt(TileIndex);

    /// <summary>True for family members; the list owner never sees purchase state.</summary>
    [JsonIgnore]
    public bool ShowPurchaseState { get; set; }

    [JsonIgnore]
    public bool ShowStripes => ShowPurchaseState && IsFullyPurchased;

    [JsonIgnore]
    public bool IsClaimedByOthers => ShowPurchaseState && IsFullyPurchased && !HasMyPurchase;

    [JsonIgnore]
    public bool ShowMyBadge => ShowPurchaseState && HasMyPurchase;

    [JsonIgnore]
    public bool ShowProgress => ShowPurchaseState && QuantityDesired > 1;

    [JsonIgnore]
    public string TakenText => $"{TotalPurchased ?? 0} of {QuantityDesired} taken";

    bool _imageFailed;

    /// <summary>Set by the view when the photo can't load, so the tile falls back to the gift.</summary>
    [JsonIgnore]
    public bool ImageFailed
    {
        get => _imageFailed;
        set
        {
            if (_imageFailed == value) return;
            _imageFailed = value;
            Raise();
            Raise(nameof(HasImage));
            Raise(nameof(ShowPlaceholder));
        }
    }

    [JsonIgnore]
    public bool HasImage => !string.IsNullOrWhiteSpace(ImageUrl) && !ImageFailed;

    [JsonIgnore]
    public bool ShowPlaceholder => !HasImage;
}
