using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using WishList.Helpers;
using WishList.Services;

namespace WishList.ViewModels;

/// <summary>
/// A look-only browser for an item's store link. It stays inside the app and only loads
/// pages on the sites the user's families allow; there is no address bar and nothing is captured.
/// </summary>
[QueryProperty(nameof(StartUrl), "url")]
[QueryProperty(nameof(ItemName), "name")]
public partial class SiteViewerViewModel : ObservableObject
{
    private readonly IApiService _apiService;
    private HashSet<string> _allowed = [];

    [ObservableProperty]
    private string _startUrl = string.Empty;

    [ObservableProperty]
    private string _itemName = string.Empty;

    /// <summary>What the WebView shows; only ever set to an allowed page.</summary>
    [ObservableProperty]
    private string _currentUrl = string.Empty;

    [ObservableProperty]
    [NotifyPropertyChangedFor(nameof(CurrentHost))]
    private string _shownUrl = string.Empty;

    [ObservableProperty]
    private bool _isLoading = true;

    /// <summary>The link itself isn't on an allowed site, so nothing is shown.</summary>
    [ObservableProperty]
    private bool _isBlocked;

    [ObservableProperty]
    private string _notice = string.Empty;

    public string CurrentHost => AllowedSites.HostOf(ShownUrl);

    public SiteViewerViewModel(IApiService apiService)
    {
        _apiService = apiService;
    }

    public async Task LoadAsync()
    {
        if (string.IsNullOrWhiteSpace(StartUrl) || CurrentUrl.Length > 0) return;

        IsLoading = true;
        try
        {
            var domains = await _apiService.GetAllowedDomainsAsync();
            _allowed = AllowedSites.ToSet(domains.Select(d => d.Domain));
        }
        catch (Exception ex)
        {
            Notice = ex.Message;
        }

        ShownUrl = StartUrl;
        if (IsAllowed(StartUrl))
        {
            CurrentUrl = StartUrl;
        }
        else
        {
            IsBlocked = true;
            IsLoading = false;
        }
    }

    public bool IsAllowed(string url) => AllowedSites.IsAllowed(url, _allowed);

    /// <summary>Called when the page tries to go somewhere it may not.</summary>
    public void ReportBlocked(string url)
    {
        var host = AllowedSites.HostOf(url);
        Notice = host.Length > 0
            ? $"{host} isn't one of your family's allowed sites."
            : "That link can't be opened here.";
    }

    public void ClearNotice() => Notice = string.Empty;

    [RelayCommand]
    private Task CloseAsync() => Shell.Current.GoToAsync("..");
}
