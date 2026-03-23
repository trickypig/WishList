using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using WishList.Models;
using WishList.Services;

namespace WishList.ViewModels;

[QueryProperty(nameof(FamilyId), "familyId")]
[QueryProperty(nameof(FamilyName), "familyName")]
public partial class FamilyListsViewModel : ObservableObject
{
    private readonly IApiService _apiService;

    public ObservableCollection<WishListModel> Lists { get; } = [];

    [ObservableProperty]
    private int _familyId;

    [ObservableProperty]
    private string _familyName = string.Empty;

    [ObservableProperty]
    private bool _isRefreshing;

    [ObservableProperty]
    private bool _isEmpty;

    [ObservableProperty]
    private string _errorMessage = string.Empty;

    public FamilyListsViewModel(IApiService apiService)
    {
        _apiService = apiService;
    }

    partial void OnFamilyIdChanged(int value)
    {
        if (value > 0)
            _ = LoadListsAsync();
    }

    [RelayCommand]
    private async Task LoadListsAsync()
    {
        ErrorMessage = string.Empty;
        try
        {
            var lists = await _apiService.GetFamilyListsAsync(FamilyId);
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
    private async Task OpenListAsync(WishListModel list)
    {
        await Shell.Current.GoToAsync($"listDetail?listId={list.Id}");
    }
}
