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

    public ObservableCollection<Item> Items { get; } = [];

    [ObservableProperty]
    private int _listId;

    [ObservableProperty]
    private WishListModel? _wishList;

    [ObservableProperty]
    private bool _isRefreshing;

    [ObservableProperty]
    private bool _isOwner;

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

            Items.Clear();
            if (list.Items != null)
            {
                foreach (var item in list.Items)
                    Items.Add(item);
            }
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
            Items.Remove(item);
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
        var index = Items.IndexOf(item);
        if (index <= 0) return;

        Items.Move(index, index - 1);
        await SaveOrderAsync();
    }

    [RelayCommand]
    private async Task MoveItemDownAsync(Item item)
    {
        var index = Items.IndexOf(item);
        if (index < 0 || index >= Items.Count - 1) return;

        Items.Move(index, index + 1);
        await SaveOrderAsync();
    }

    private async Task SaveOrderAsync()
    {
        try
        {
            var ids = Items.Select(i => i.Id).ToList();
            await _apiService.ReorderItemsAsync(ListId, ids);
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }
}
