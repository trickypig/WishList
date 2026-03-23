using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using WishList.Services;

namespace WishList.ViewModels;

public partial class SettingsViewModel : ObservableObject
{
    private readonly IAuthService _authService;

    public string DisplayName => _authService.CurrentUser?.DisplayName ?? "";
    public string Email => _authService.CurrentUser?.Email ?? "";
    public bool IsAdmin => (_authService.CurrentUser?.IsAdmin ?? 0) == 1;
    public bool IsChild => (_authService.CurrentUser?.IsChild ?? 0) == 1;

    public SettingsViewModel(IAuthService authService)
    {
        _authService = authService;
    }

    [RelayCommand]
    private async Task LogoutAsync()
    {
        _authService.Logout();
        await Shell.Current.GoToAsync("//login");
    }
}
