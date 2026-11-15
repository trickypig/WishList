namespace WishList.Models;

/// <summary>
/// One block of the list page: either the ungrouped items at the top (Group is null) or a group and its items.
/// </summary>
public class ItemSection
{
    public ItemSection(ItemGroup? group, List<Item> items, bool showEmptyHint, bool isSuggestions = false)
    {
        Group = group;
        Items = items;
        ShowEmptyHint = showEmptyHint && items.Count == 0;
        IsSuggestions = isSuggestions;
    }

    public ItemGroup? Group { get; }
    public List<Item> Items { get; }

    public int? GroupId => Group?.Id;
    public bool IsGroup => Group != null;
    public bool IsSuggestions { get; }
    public bool HasHeading => IsGroup || IsSuggestions;
    public string Title => IsSuggestions ? "Family suggestions" : Group?.Title ?? string.Empty;
    public string Description => Group?.Description ?? string.Empty;
    public bool HasDescription => !string.IsNullOrWhiteSpace(Group?.Description);

    /// <summary>Owner-only placeholder that doubles as a drop target when the section has no items.</summary>
    public bool ShowEmptyHint { get; }

    public string EmptyHint => IsSuggestions ? "No family suggestions to show." : IsGroup ? "Drag items here" : "Drop here to take an item out of its group";
}
