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

    [ObservableProperty]
    private string _currentPassword = "";

    [ObservableProperty]
    private string _newPassword = "";

    [ObservableProperty]
    private string _confirmPassword = "";

    [ObservableProperty]
    private string _passwordMessage = "";

    [ObservableProperty]
    private bool _isPasswordError;

    [ObservableProperty]
    private bool _isChangingPassword;

    public SettingsViewModel(IAuthService authService)
    {
        _authService = authService;
    }

    [RelayCommand]
    private async Task ChangePasswordAsync()
    {
        PasswordMessage = "";

        if (NewPassword != ConfirmPassword)
        {
            PasswordMessage = "New passwords do not match";
            IsPasswordError = true;
            return;
        }

        if (NewPassword.Length < 6)
        {
            PasswordMessage = "New password must be at least 6 characters";
            IsPasswordError = true;
            return;
        }

        IsChangingPassword = true;
        try
        {
            await _authService.ChangePasswordAsync(CurrentPassword, NewPassword, ConfirmPassword);
            PasswordMessage = "Password updated successfully";
            IsPasswordError = false;
            CurrentPassword = "";
            NewPassword = "";
            ConfirmPassword = "";
        }
        catch (ApiException ex)
        {
            PasswordMessage = ex.Message;
            IsPasswordError = true;
        }
        catch (Exception)
        {
            PasswordMessage = "Failed to change password";
            IsPasswordError = true;
        }
        finally
        {
            IsChangingPassword = false;
        }
    }

    [RelayCommand]
    private async Task LogoutAsync()
    {
        _authService.Logout();
        await Shell.Current.GoToAsync("//login");
    }
}
