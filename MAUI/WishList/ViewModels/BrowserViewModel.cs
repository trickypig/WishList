#if WINDOWS
using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using CommunityToolkit.Mvvm.Messaging;
using WishList.Models;
using WishList.Services;

namespace WishList.ViewModels;

[QueryProperty(nameof(ListId), "listId")]
public partial class BrowserViewModel : ObservableObject
{
    private readonly IApiService _apiService;
    private readonly IAuthService _authService;

    public ObservableCollection<AllowedDomain> AllowedDomains { get; } = [];

    private HashSet<string> _allowedDomainSet = [];

    [ObservableProperty]
    private int _listId;

    [ObservableProperty]
    private string _currentUrl = string.Empty;

    [ObservableProperty]
    private string _errorMessage = string.Empty;

    [ObservableProperty]
    private bool _isScraping;

    [ObservableProperty]
    private bool _showQuickLaunch = true;

    [ObservableProperty]
    private bool _showBrowser;

    public BrowserViewModel(IApiService apiService, IAuthService authService)
    {
        _apiService = apiService;
        _authService = authService;
    }

    [RelayCommand]
    public async Task LoadDomainsAsync()
    {
        try
        {
            // Get domains from all user's families (union)
            var families = await _apiService.GetFamiliesAsync();
            var allDomains = new Dictionary<string, AllowedDomain>();

            foreach (var family in families)
            {
                var domains = await _apiService.GetFamilyDomainsAsync(family.Id);
                foreach (var d in domains)
                {
                    allDomains.TryAdd(d.Domain, d);
                }
            }

            AllowedDomains.Clear();
            _allowedDomainSet.Clear();
            foreach (var d in allDomains.Values.OrderBy(d => d.Domain))
            {
                AllowedDomains.Add(d);
                _allowedDomainSet.Add(d.Domain.ToLowerInvariant());
            }
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }

    /// <summary>
    /// Check if a URL's domain is in the allowed list.
    /// </summary>
    public bool IsDomainAllowed(string url)
    {
        if (!Uri.TryCreate(url, UriKind.Absolute, out var uri))
            return false;

        var host = uri.Host.ToLowerInvariant();

        // Check exact match and parent domain matches (e.g., www.amazon.com matches amazon.com)
        foreach (var allowed in _allowedDomainSet)
        {
            if (host == allowed || host.EndsWith("." + allowed))
                return true;
        }

        return false;
    }

    [RelayCommand]
    private void OpenSite(AllowedDomain domain)
    {
        CurrentUrl = $"https://www.{domain.Domain}";
        ShowQuickLaunch = false;
        ShowBrowser = true;
    }

    [RelayCommand]
    private async Task CaptureItemAsync()
    {
        if (string.IsNullOrWhiteSpace(CurrentUrl))
            return;

        IsScraping = true;
        ErrorMessage = string.Empty;

        try
        {
            var result = await _apiService.ScrapeUrlAsync(CurrentUrl);

            // Navigate back FIRST so ItemFormPage.OnAppearing re-registers the handler
            await Shell.Current.GoToAsync("..");

            // THEN pass the result back via WeakReferenceMessenger
            WeakReferenceMessenger.Default.Send(new ItemCapturedMessage(result));
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
        finally
        {
            IsScraping = false;
        }
    }

    [RelayCommand]
    private void BackToQuickLaunch()
    {
        ShowBrowser = false;
        ShowQuickLaunch = true;
        CurrentUrl = string.Empty;
    }
}
#endif
