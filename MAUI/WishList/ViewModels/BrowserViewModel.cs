#if WINDOWS
using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using CommunityToolkit.Mvvm.Messaging;
using WishList.Helpers;
using WishList.Models;
using WishList.Services;

namespace WishList.ViewModels;

/// <summary>The page currently showing in the in-app browser.</summary>
public record BrowserPageContent(string Url, string Html);

[QueryProperty(nameof(ListId), "listId")]
[QueryProperty(nameof(StartUrl), "startUrl")]
public partial class BrowserViewModel : ObservableObject
{
    private readonly IApiService _apiService;
    private readonly IAuthService _authService;

    /// <summary>Set by BrowserPage: reads the page out of the WebView.</summary>
    public Func<Task<BrowserPageContent?>>? ReadCurrentPage { get; set; }

    /// <summary>A pasted link to open once the allowed domains have loaded.</summary>
    public string StartUrl { get; set; } = string.Empty;

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
            var domains = await _apiService.GetAllowedDomainsAsync();
            AllowedDomains.Clear();
            foreach (var d in domains)
                AllowedDomains.Add(d);
            _allowedDomainSet = AllowedSites.ToSet(domains.Select(d => d.Domain));
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }

    /// <summary>
    /// Check if a URL's domain is in the allowed list.
    /// </summary>
    public bool IsDomainAllowed(string url) => AllowedSites.IsAllowed(url, _allowedDomainSet);

    [RelayCommand]
    private void OpenSite(AllowedDomain domain)
    {
        CurrentUrl = $"https://{domain.Domain}";
        ShowQuickLaunch = false;
        ShowBrowser = true;
    }

    /// <summary>
    /// Opens a pasted link. Sites outside the allowed list stay out of the browser,
    /// so those links are fetched by the server instead.
    /// </summary>
    public async Task OpenStartUrlAsync()
    {
        if (string.IsNullOrWhiteSpace(StartUrl))
            return;

        var url = StartUrl;
        StartUrl = string.Empty;

        if (IsDomainAllowed(url))
        {
            CurrentUrl = url;
            ShowQuickLaunch = false;
            ShowBrowser = true;
        }
        else
        {
            await CaptureAsync(() => _apiService.ScrapeUrlAsync(url));
        }
    }

    [RelayCommand]
    private async Task CaptureItemAsync()
    {
        if (string.IsNullOrWhiteSpace(CurrentUrl))
            return;

        await CaptureAsync(async () =>
        {
            var page = ReadCurrentPage == null ? null : await ReadCurrentPage();
            if (page == null || string.IsNullOrEmpty(page.Html))
                throw new InvalidOperationException("Couldn't read this page. Wait for it to finish loading and try again.");

            return await _apiService.ScrapeHtmlAsync(page.Url, page.Html);
        });
    }

    private async Task CaptureAsync(Func<Task<ScrapeResult>> getResult)
    {
        IsScraping = true;
        ErrorMessage = string.Empty;

        try
        {
            var result = await getResult();

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
