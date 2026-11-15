using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using WishList.Models;
using WishList.Services;

namespace WishList.ViewModels;

public partial class DashboardViewModel : ObservableObject
{
    private readonly IApiService _apiService;
    private readonly IAuthService _authService;

    /// <summary>Your lists first, then one section per family with the lists its members share.</summary>
    /// <remarks>
    /// Replaced whole on every load, never edited in place: WinUI's grouped list crashes
    /// the app (0xc000027b) when its groups are cleared and re-added while it's showing.
    /// </remarks>
    [ObservableProperty]
    private IReadOnlyList<ListSection> _sections = [];

    [ObservableProperty]
    private bool _isRefreshing;

    [ObservableProperty]
    private string _errorMessage = string.Empty;

    public string UserDisplayName => _authService.CurrentUser?.DisplayName ?? "User";

    public DashboardViewModel(IApiService apiService, IAuthService authService)
    {
        _apiService = apiService;
        _authService = authService;
    }

    [RelayCommand]
    private async Task LoadListsAsync()
    {
        ErrorMessage = string.Empty;
        try
        {
            var mineTask = _apiService.GetListsAsync();
            var familiesTask = _apiService.GetFamiliesAsync();
            await Task.WhenAll(mineTask, familiesTask);
            var mine = mineTask.Result;
            var families = familiesTask.Result;
            var familyLists = await Task.WhenAll(families.Select(f => _apiService.GetFamilyListsAsync(f.Id)));

            var sections = new List<ListSection> { new("My Lists", string.Empty, true, mine) };
            for (var i = 0; i < families.Count; i++)
                sections.Add(new ListSection(families[i].Name, "Lists shared with this family", false, familyLists[i]));
            Sections = sections;
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
    private async Task CreateListAsync()
    {
        var title = await Shell.Current.DisplayPromptAsync("New List", "Enter a title for your wish list:");
        if (string.IsNullOrWhiteSpace(title)) return;

        try
        {
            await _apiService.CreateListAsync(title, "", "all_families");
            await LoadListsAsync();
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }

    [RelayCommand]
    private async Task OpenListAsync(WishListModel list)
    {
        await Shell.Current.GoToAsync($"listDetail?listId={list.Id}");
    }

    [RelayCommand]
    private async Task DeleteListAsync(WishListModel list)
    {
        if (!list.IsMine) return;
        var confirm = await Shell.Current.DisplayAlert("Delete List", $"Delete \"{list.Title}\"?", "Delete", "Cancel");
        if (!confirm) return;

        try
        {
            await _apiService.DeleteListAsync(list.Id);
            await LoadListsAsync();
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }
}
