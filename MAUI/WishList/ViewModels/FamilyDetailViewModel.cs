using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using WishList.Models;
using WishList.Services;

namespace WishList.ViewModels;

[QueryProperty(nameof(FamilyId), "familyId")]
public partial class FamilyDetailViewModel : ObservableObject
{
    private readonly IApiService _apiService;
    private readonly IAuthService _authService;

    public ObservableCollection<FamilyMember> Members { get; } = [];
    public ObservableCollection<AllowedDomain> Domains { get; } = [];

    [ObservableProperty]
    private int _familyId;

    [ObservableProperty]
    private Family? _family;

    [ObservableProperty]
    private bool _isAdmin;

    [ObservableProperty]
    private bool _isParent;

    [ObservableProperty]
    private string _errorMessage = string.Empty;

    [ObservableProperty]
    private bool _isRefreshing;

    [ObservableProperty]
    private string _inviteEmail = string.Empty;

    [ObservableProperty]
    private bool _copied;

    public FamilyDetailViewModel(IApiService apiService, IAuthService authService)
    {
        _apiService = apiService;
        _authService = authService;
    }

    partial void OnFamilyIdChanged(int value)
    {
        if (value > 0)
            _ = LoadFamilyAsync();
    }

    [RelayCommand]
    private async Task LoadFamilyAsync()
    {
        ErrorMessage = string.Empty;
        try
        {
            var family = await _apiService.GetFamilyAsync(FamilyId);
            Family = family;
            IsAdmin = family.EffectiveRole == "admin";
            IsParent = IsAdmin && (_authService.CurrentUser?.IsChild ?? 0) == 0;

            Members.Clear();
            if (family.Members != null)
            {
                foreach (var m in family.Members)
                    Members.Add(m);
            }

            // Load domains
            var domains = await _apiService.GetFamilyDomainsAsync(FamilyId);
            Domains.Clear();
            foreach (var d in domains)
                Domains.Add(d);
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
    private async Task CopyInviteCodeAsync()
    {
        if (Family?.InviteCode is { Length: > 0 } code)
        {
            await Clipboard.Default.SetTextAsync(code);
            Copied = true;
            await Task.Delay(2000);
            Copied = false;
        }
    }

    [RelayCommand]
    private async Task InviteAsync()
    {
        if (string.IsNullOrWhiteSpace(InviteEmail)) return;

        try
        {
            await _apiService.InviteToFamilyAsync(FamilyId, InviteEmail);
            await Shell.Current.DisplayAlert("Success", $"Invite sent to {InviteEmail}", "OK");
            InviteEmail = string.Empty;
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }

    [RelayCommand]
    private async Task RemoveMemberAsync(FamilyMember member)
    {
        var confirm = await Shell.Current.DisplayAlert("Remove Member",
            $"Remove {member.DisplayName} from the family?", "Remove", "Cancel");
        if (!confirm) return;

        try
        {
            await _apiService.RemoveFamilyMemberAsync(FamilyId, member.Id);
            await LoadFamilyAsync();
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }

    [RelayCommand]
    private async Task ToggleChildAsync(FamilyMember member)
    {
        try
        {
            var newStatus = member.IsChild == 0 ? 1 : 0;
            await _apiService.SetChildStatusAsync(FamilyId, member.Id, newStatus);
            await LoadFamilyAsync();
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }

    [RelayCommand]
    private async Task LeaveAsync()
    {
        var confirm = await Shell.Current.DisplayAlert("Leave Family",
            "Are you sure you want to leave this family?", "Leave", "Cancel");
        if (!confirm) return;

        try
        {
            var userId = _authService.CurrentUser!.Id;
            await _apiService.RemoveFamilyMemberAsync(FamilyId, userId);
            await Shell.Current.GoToAsync("..");
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }

    [RelayCommand]
    private async Task AddDomainAsync()
    {
        var domain = await Shell.Current.DisplayPromptAsync("Add Domain", "Enter domain (e.g. amazon.com):");
        if (string.IsNullOrWhiteSpace(domain)) return;

        var displayName = await Shell.Current.DisplayPromptAsync("Display Name", "Enter display name (optional):", initialValue: "");

        try
        {
            await _apiService.AddFamilyDomainAsync(FamilyId, domain, displayName ?? "");
            await LoadFamilyAsync();
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }

    [RelayCommand]
    private async Task RemoveDomainAsync(AllowedDomain domain)
    {
        var confirm = await Shell.Current.DisplayAlert("Remove Domain",
            $"Remove {domain.Domain} from allowed domains?", "Remove", "Cancel");
        if (!confirm) return;

        try
        {
            await _apiService.RemoveFamilyDomainAsync(FamilyId, domain.Domain);
            await LoadFamilyAsync();
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }
}
