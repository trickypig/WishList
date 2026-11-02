using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using WishList.Services;

namespace WishList.ViewModels;

public partial class ForgotPasswordViewModel : ObservableObject
{
    private readonly IAuthService _authService;

    [ObservableProperty]
    private string _email = string.Empty;

    [ObservableProperty]
    private string _code = string.Empty;

    [ObservableProperty]
    private string _newPassword = string.Empty;

    [ObservableProperty]
    private string _confirmPassword = string.Empty;

    [ObservableProperty]
    private string _errorMessage = string.Empty;

    [ObservableProperty]
    private string _successMessage = string.Empty;

    [ObservableProperty]
    [NotifyPropertyChangedFor(nameof(IsEmailStep))]
    private bool _isCodeStep;

    [ObservableProperty]
    private bool _isBusy;

    public bool IsEmailStep => !IsCodeStep;

    public ForgotPasswordViewModel(IAuthService authService)
    {
        _authService = authService;
    }

    [RelayCommand]
    private async Task SendCodeAsync()
    {
        if (string.IsNullOrWhiteSpace(Email))
        {
            ErrorMessage = "Please enter your email.";
            return;
        }

        IsBusy = true;
        ErrorMessage = string.Empty;

        try
        {
            await _authService.RequestPasswordResetAsync(Email.Trim());
            SuccessMessage = "If an account exists for that email, a reset code is on its way.";
            IsCodeStep = true;
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
        finally
        {
            IsBusy = false;
        }
    }

    [RelayCommand]
    private async Task ResetPasswordAsync()
    {
        if (string.IsNullOrWhiteSpace(Code) || string.IsNullOrEmpty(NewPassword))
        {
            ErrorMessage = "Please enter the code and a new password.";
            return;
        }

        if (NewPassword != ConfirmPassword)
        {
            ErrorMessage = "New passwords do not match.";
            return;
        }

        IsBusy = true;
        ErrorMessage = string.Empty;
        SuccessMessage = string.Empty;

        try
        {
            await _authService.ResetPasswordAsync(Email.Trim(), Code.Trim(), NewPassword, ConfirmPassword);
            ClearForm();
            await Shell.Current.DisplayAlertAsync("Password reset", "Your password has been reset. You can now sign in.", "OK");
            await Shell.Current.GoToAsync("//login");
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
        finally
        {
            IsBusy = false;
        }
    }

    [RelayCommand]
    private void RequestAnotherCode()
    {
        Code = string.Empty;
        ErrorMessage = string.Empty;
        SuccessMessage = string.Empty;
        IsCodeStep = false;
    }

    [RelayCommand]
    private async Task GoToLoginAsync()
    {
        ClearForm();
        await Shell.Current.GoToAsync("//login");
    }

    private void ClearForm()
    {
        Email = string.Empty;
        Code = string.Empty;
        NewPassword = string.Empty;
        ConfirmPassword = string.Empty;
        ErrorMessage = string.Empty;
        SuccessMessage = string.Empty;
        IsCodeStep = false;
    }
}
