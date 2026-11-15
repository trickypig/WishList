using WishList.Helpers;
using WishList.ViewModels;

namespace WishList.Views;

public partial class SiteViewerPage : ContentPage
{
    private readonly SiteViewerViewModel _viewModel;

    public SiteViewerPage(SiteViewerViewModel viewModel)
    {
        InitializeComponent();
        BindingContext = _viewModel = viewModel;
#if WINDOWS
        Viewer.HandlerChanged += OnViewerHandlerChanged;
#endif
    }

    protected override async void OnAppearing()
    {
        base.OnAppearing();
        await _viewModel.LoadAsync();
    }

    private void OnBackClicked(object? sender, EventArgs e)
    {
        if (Viewer.CanGoBack)
            Viewer.GoBack();
    }

    /// <summary>Every page load, link tap and redirect passes through here: only allowed web pages get through.</summary>
    private void OnNavigating(object? sender, WebNavigatingEventArgs e)
    {
        if (string.IsNullOrEmpty(e.Url) || e.Url.StartsWith("about:", StringComparison.OrdinalIgnoreCase))
            return;

        if (_viewModel.IsAllowed(e.Url))
        {
            _viewModel.ClearNotice();
            _viewModel.IsLoading = true;
            return;
        }

        // Other sites, app links (intent:, market:, tel:, mailto: ...) and scripts all stay out
        e.Cancel = true;
        _viewModel.ReportBlocked(e.Url);
    }

    private void OnNavigated(object? sender, WebNavigatedEventArgs e)
    {
        _viewModel.IsLoading = false;
        if (AllowedSites.IsWebUrl(e.Url))
            _viewModel.ShownUrl = e.Url;
        BackButton.IsEnabled = Viewer.CanGoBack;
    }

#if WINDOWS
    private bool _locked;

    private void OnViewerHandlerChanged(object? sender, EventArgs e)
    {
        if (Viewer.Handler?.PlatformView is not Microsoft.UI.Xaml.Controls.WebView2 native) return;

        if (native.CoreWebView2 != null)
            Lock(native.CoreWebView2);
        else
            native.CoreWebView2Initialized += (s, args) => Lock(native.CoreWebView2);
    }

    /// <summary>
    /// WebView2 would otherwise open new windows, offer "open in new window" and dev tools
    /// on right-click, and save downloads, any of which is a way out of the app.
    /// </summary>
    private void Lock(Microsoft.Web.WebView2.Core.CoreWebView2? core)
    {
        if (_locked || core == null) return;
        _locked = true;

        core.Settings.AreDefaultContextMenusEnabled = false;
        core.Settings.AreDevToolsEnabled = false;
        core.Settings.IsStatusBarEnabled = false;

        core.NewWindowRequested += (s, args) =>
        {
            args.Handled = true;
            if (_viewModel.IsAllowed(args.Uri))
                Viewer.Source = new UrlWebViewSource { Url = args.Uri };
            else
                _viewModel.ReportBlocked(args.Uri);
        };

        core.DownloadStarting += (s, args) => args.Cancel = true;
    }
#endif
}
