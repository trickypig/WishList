using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using WishList.Models;
using WishList.Services;

namespace WishList.ViewModels;

[QueryProperty(nameof(ListId), "listId")]
public partial class ListDetailViewModel : ObservableObject
{
    private readonly IApiService _apiService;
    private readonly IAuthService _authService;

    /// <summary>The ungrouped items first, then one section per group, holding only items the filter lets through.</summary>
    public ObservableCollection<ItemSection> Sections { get; } = [];

    // Every item on the list in saved order, and the list's groups
    private List<Item> _allItems = [];
    private List<ItemGroup> _groups = [];

    [ObservableProperty]
    private int _listId;

    [ObservableProperty]
    private WishListModel? _wishList;

    [ObservableProperty]
    private bool _isRefreshing;

    [ObservableProperty]
    [NotifyPropertyChangedFor(nameof(EmptyMessage))]
    private bool _isOwner;

    /// <summary>On someone else's list, whether to include items that are fully purchased.</summary>
    [ObservableProperty]
    [NotifyPropertyChangedFor(nameof(EmptyMessage))]
    private bool _showPurchased;

    public string EmptyMessage =>
        !IsOwner && !ShowPurchased && _allItems.Count > 0
            ? "Everything on this list has been purchased."
            : "No items in this list yet.";

    partial void OnShowPurchasedChanged(bool value) => ApplyFilter();

    [RelayCommand]
    private void ToggleShowPurchased() => ShowPurchased = !ShowPurchased;

    /// <summary>The section key of an item: its group id, or null if ungrouped or its group no longer exists.</summary>
    private int? SectionOf(Item item) =>
        item.GroupId is int id && _groups.Any(g => g.Id == id) ? id : null;

    private IEnumerable<int?> SectionKeys => new int?[] { null }.Concat(_groups.Select(g => (int?)g.Id));

    private void ApplyFilter()
    {
        Sections.Clear();
        var visible = _allItems.Where(i => IsOwner || ShowPurchased || !i.IsFullyPurchased).ToList();

        // Empty sections are only worth showing to the owner, as somewhere to drop items
        var ungrouped = visible.Where(i => SectionOf(i) == null).ToList();
        if (ungrouped.Count > 0 || (IsOwner && _groups.Count > 0))
            Sections.Add(new ItemSection(null, ungrouped, IsOwner));

        foreach (var group in _groups)
        {
            var items = visible.Where(i => SectionOf(i) == group.Id).ToList();
            if (items.Count > 0 || IsOwner)
                Sections.Add(new ItemSection(group, items, IsOwner));
        }

        OnPropertyChanged(nameof(EmptyMessage));
    }

    [ObservableProperty]
    private string _errorMessage = string.Empty;

    public ListDetailViewModel(IApiService apiService, IAuthService authService)
    {
        _apiService = apiService;
        _authService = authService;
    }

    partial void OnListIdChanged(int value)
    {
        if (value > 0)
            _ = LoadListAsync();
    }

    [RelayCommand]
    private async Task LoadListAsync()
    {
        ErrorMessage = string.Empty;
        try
        {
            var list = await _apiService.GetListAsync(ListId);
            WishList = list;
            IsOwner = list.UserId == _authService.CurrentUser?.Id;

            _allItems = list.Items ?? [];
            _groups = list.Groups ?? [];
            ApplyFilter();
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
        finally
        {
            IsRefreshing = false;
        }
    }

    [RelayCommand]
    private async Task EditListAsync()
    {
        if (WishList == null) return;

        var title = await Shell.Current.DisplayPromptAsync("Edit List", "Title:", initialValue: WishList.Title);
        if (title == null) return; // cancelled

        var description = await Shell.Current.DisplayPromptAsync("Edit List", "Description:", initialValue: WishList.Description);
        if (description == null) description = WishList.Description;

        try
        {
            await _apiService.UpdateListAsync(ListId, title, description);
            await LoadListAsync();
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }

    [RelayCommand]
    private async Task ChangeSharingAsync()
    {
        if (WishList == null) return;

        var choice = await Shell.Current.DisplayActionSheet(
            "List Visibility", "Cancel", null,
            "All Families", "Selected Families", "Private");

        if (choice == null || choice == "Cancel") return;

        var visibility = choice switch
        {
            "All Families" => "all_families",
            "Selected Families" => "selected_families",
            "Private" => "private",
            _ => WishList.Visibility
        };

        var familyIds = new List<int>();

        if (visibility == "selected_families")
        {
            // Fetch user's families and let them pick
            try
            {
                var families = await _apiService.GetFamiliesAsync();
                if (families.Count == 0)
                {
                    await Shell.Current.DisplayAlert("No Families", "You are not a member of any families.", "OK");
                    return;
                }

                // Let the user pick families one by one
                var selectedFamilies = new List<Family>();
                var remainingFamilies = new List<Family>(families);

                while (remainingFamilies.Count > 0)
                {
                    var options = remainingFamilies.Select(f => f.Name).ToList();
                    options.Add("Done selecting");

                    var selected = await Shell.Current.DisplayActionSheet(
                        $"Select families to share with ({selectedFamilies.Count} selected)",
                        "Cancel", null, options.ToArray());

                    if (selected == null || selected == "Cancel") return;
                    if (selected == "Done selecting") break;

                    var family = remainingFamilies.First(f => f.Name == selected);
                    selectedFamilies.Add(family);
                    remainingFamilies.Remove(family);
                }

                if (selectedFamilies.Count == 0)
                {
                    await Shell.Current.DisplayAlert("No Selection", "You must select at least one family.", "OK");
                    return;
                }

                familyIds = selectedFamilies.Select(f => f.Id).ToList();
            }
            catch (Exception ex)
            {
                ErrorMessage = ex.Message;
                return;
            }
        }

        try
        {
            await _apiService.UpdateListSharingAsync(ListId, visibility, familyIds);
            await LoadListAsync();
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }

    public string VisibilityLabel => WishList?.Visibility switch
    {
        "all_families" => "Shared with all families",
        "selected_families" => "Shared with selected families",
        "private" => "Private",
        _ => ""
    };

    partial void OnWishListChanged(WishListModel? value)
    {
        OnPropertyChanged(nameof(VisibilityLabel));
    }

    [RelayCommand]
    private async Task AddItemAsync()
    {
        await Shell.Current.GoToAsync($"itemForm?listId={ListId}");
    }

    [RelayCommand]
    private async Task EditItemAsync(Item item)
    {
        await Shell.Current.GoToAsync($"itemForm?listId={ListId}&itemId={item.Id}");
    }

    [RelayCommand]
    private async Task DeleteItemAsync(Item item)
    {
        var confirm = await Shell.Current.DisplayAlert("Delete Item", $"Delete \"{item.Name}\"?", "Delete", "Cancel");
        if (!confirm) return;

        try
        {
            await _apiService.DeleteItemAsync(item.Id);
            _allItems.Remove(item);
            ApplyFilter();
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }

    [RelayCommand]
    private async Task PurchaseItemAsync(Item item)
    {
        try
        {
            var remaining = item.QuantityDesired - (item.TotalPurchased ?? 0);
            if (remaining <= 0) return;

            await _apiService.PurchaseItemAsync(item.Id, 1);
            await LoadListAsync();
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }

    [RelayCommand]
    private async Task UnpurchaseItemAsync(Item item)
    {
        try
        {
            await _apiService.UnpurchaseItemAsync(item.Id);
            await LoadListAsync();
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }

    [RelayCommand]
    private async Task MoveItemUpAsync(Item item)
    {
        var section = _allItems.Where(i => SectionOf(i) == SectionOf(item)).ToList();
        var index = section.IndexOf(item);
        if (index <= 0) return;

        await MoveItemAsync(item, SectionOf(item), section[index - 1]);
    }

    [RelayCommand]
    private async Task MoveItemDownAsync(Item item)
    {
        var section = _allItems.Where(i => SectionOf(i) == SectionOf(item)).ToList();
        var index = section.IndexOf(item);
        if (index < 0 || index >= section.Count - 1) return;

        await MoveItemAsync(item, SectionOf(item), index + 2 < section.Count ? section[index + 2] : null);
    }

    /// <summary>
    /// Moves an item into a section (null = ungrouped), ahead of <paramref name="before"/> or at the end,
    /// then saves the order and group of every item. Only the owner moves items, and their view is never filtered.
    /// </summary>
    public async Task MoveItemAsync(Item item, int? toGroupId, Item? before)
    {
        if (!IsOwner || ReferenceEquals(item, before)) return;

        var sections = SectionKeys.ToDictionary(
            k => k ?? 0,
            k => _allItems.Where(i => SectionOf(i) == k && !ReferenceEquals(i, item)).ToList());
        if (!sections.TryGetValue(toGroupId ?? 0, out var target)) return;

        var at = before == null ? -1 : target.IndexOf(before);
        target.Insert(at < 0 ? target.Count : at, item);

        var ordered = SectionKeys.SelectMany(k => sections[k ?? 0].Select(i => (Item: i, GroupId: k))).ToList();
        var unchanged = ordered.Select(o => o.Item).SequenceEqual(_allItems) && SectionOf(item) == toGroupId;
        if (unchanged) return;

        // Show the new arrangement straight away, then save it
        for (var n = 0; n < ordered.Count; n++)
        {
            ordered[n].Item.GroupId = ordered[n].GroupId;
            ordered[n].Item.SortOrder = n;
        }
        _allItems = ordered.Select(o => o.Item).ToList();
        ApplyFilter();

        try
        {
            await _apiService.ArrangeItemsAsync(ListId, ordered.Select(o => (o.Item.Id, o.GroupId)));
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
            await LoadListAsync();
        }
    }

    /// <summary>Drop onto another item's card: lands just before it, or just after when dropped on its lower half.</summary>
    public Task DropOnItemAsync(Item dragged, Item target, bool after)
    {
        var group = SectionOf(target);
        Item? before = target;
        if (after)
        {
            var section = _allItems.Where(i => SectionOf(i) == group && !ReferenceEquals(i, dragged)).ToList();
            var index = section.IndexOf(target);
            before = index + 1 < section.Count ? section[index + 1] : null;
        }
        return MoveItemAsync(dragged, group, before);
    }

    /// <summary>Drop onto a group's header or an empty section: lands at the end of it.</summary>
    public Task DropOnSectionAsync(Item dragged, ItemSection section) =>
        MoveItemAsync(dragged, section.GroupId, null);

    [RelayCommand]
    private async Task AddGroupAsync()
    {
        var title = await Shell.Current.DisplayPromptAsync("New Group", "Title:");
        if (string.IsNullOrWhiteSpace(title)) return;

        var description = await Shell.Current.DisplayPromptAsync("New Group", "Description (optional):") ?? string.Empty;

        try
        {
            await _apiService.CreateGroupAsync(ListId, title.Trim(), description);
            await LoadListAsync();
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }

    [RelayCommand]
    private async Task EditGroupAsync(ItemSection section)
    {
        if (section.Group is not { } group) return;

        var title = await Shell.Current.DisplayPromptAsync("Edit Group", "Title:", initialValue: group.Title);
        if (string.IsNullOrWhiteSpace(title)) return;

        var description = await Shell.Current.DisplayPromptAsync("Edit Group", "Description:", initialValue: group.Description ?? string.Empty)
                          ?? group.Description ?? string.Empty;

        try
        {
            await _apiService.UpdateGroupAsync(group.Id, title.Trim(), description);
            await LoadListAsync();
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }

    [RelayCommand]
    private async Task DeleteGroupAsync(ItemSection section)
    {
        if (section.Group is not { } group) return;

        var confirm = await Shell.Current.DisplayAlert("Delete Group",
            $"Delete the group \"{group.Title}\"? Its items will stay on the list, just outside any group.",
            "Delete", "Cancel");
        if (!confirm) return;

        try
        {
            await _apiService.DeleteGroupAsync(group.Id);
            await LoadListAsync();
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }
}
