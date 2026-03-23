using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using WishList.Models;
using WishList.Services;

namespace WishList.ViewModels;

public partial class FamiliesViewModel : ObservableObject
{
    private readonly IApiService _apiService;

    public ObservableCollection<Family> Families { get; } = [];

    [ObservableProperty]
    private bool _isRefreshing;

    [ObservableProperty]
    private bool _isEmpty;

    [ObservableProperty]
    private string _errorMessage = string.Empty;

    public FamiliesViewModel(IApiService apiService)
    {
        _apiService = apiService;
    }

    [RelayCommand]
    private async Task LoadFamiliesAsync()
    {
        ErrorMessage = string.Empty;
        try
        {
            var families = await _apiService.GetFamiliesAsync();
            Families.Clear();
            foreach (var f in families)
                Families.Add(f);
            IsEmpty = Families.Count == 0;
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
    private async Task CreateFamilyAsync()
    {
        var name = await Shell.Current.DisplayPromptAsync("New Family", "Enter a name for your family:");
        if (string.IsNullOrWhiteSpace(name)) return;

        try
        {
            await _apiService.CreateFamilyAsync(name);
            await LoadFamiliesAsync();
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }

    [RelayCommand]
    private async Task JoinFamilyAsync()
    {
        var code = await Shell.Current.DisplayPromptAsync("Join Family", "Enter the invite code:");
        if (string.IsNullOrWhiteSpace(code)) return;

        try
        {
            await _apiService.JoinFamilyAsync(code);
            await LoadFamiliesAsync();
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }

    [RelayCommand]
    private async Task OpenFamilyAsync(Family family)
    {
        await Shell.Current.GoToAsync($"familyDetail?familyId={family.Id}");
    }

    [RelayCommand]
    private async Task ViewFamilyListsAsync(Family family)
    {
        await Shell.Current.GoToAsync($"familyLists?familyId={family.Id}&familyName={Uri.EscapeDataString(family.Name)}");
    }
}
