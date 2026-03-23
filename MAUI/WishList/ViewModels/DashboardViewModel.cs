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

    public ObservableCollection<WishListModel> Lists { get; } = [];

    [ObservableProperty]
    private bool _isRefreshing;

    [ObservableProperty]
    private bool _isEmpty;

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
            var lists = await _apiService.GetListsAsync();
            Lists.Clear();
            foreach (var list in lists)
                Lists.Add(list);
            IsEmpty = Lists.Count == 0;
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
        var confirm = await Shell.Current.DisplayAlert("Delete List", $"Delete \"{list.Title}\"?", "Delete", "Cancel");
        if (!confirm) return;

        try
        {
            await _apiService.DeleteListAsync(list.Id);
            Lists.Remove(list);
            IsEmpty = Lists.Count == 0;
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }
}
