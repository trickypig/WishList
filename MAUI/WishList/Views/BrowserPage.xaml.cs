#if WINDOWS
using Microsoft.UI.Xaml.Controls;
using Microsoft.Web.WebView2.Core;
using WishList.ViewModels;

namespace WishList.Views;

public partial class BrowserPage : ContentPage
{
    private readonly BrowserViewModel _viewModel;
    private bool _newWindowHandlerAttached;

    public BrowserPage(BrowserViewModel viewModel)
    {
        InitializeComponent();
        BindingContext = _viewModel = viewModel;
        BrowserWebView.HandlerChanged += OnWebViewHandlerChanged;
    }

    protected override async void OnAppearing()
    {
        base.OnAppearing();
        await _viewModel.LoadDomainsAsync();
    }

    private void OnWebViewHandlerChanged(object? sender, EventArgs e)
    {
        if (_newWindowHandlerAttached)
            return;

        if (BrowserWebView.Handler?.PlatformView is WebView2 nativeWebView)
        {
            if (nativeWebView.CoreWebView2 != null)
            {
                AttachNewWindowHandler(nativeWebView);
            }
            else
            {
                nativeWebView.CoreWebView2Initialized += (s, args) =>
                {
                    AttachNewWindowHandler(nativeWebView);
                };
            }
        }
    }

    private void AttachNewWindowHandler(WebView2 nativeWebView)
    {
        if (_newWindowHandlerAttached)
            return;

        _newWindowHandlerAttached = true;
        nativeWebView.CoreWebView2.NewWindowRequested += (s, args) =>
        {
            // Prevent opening in external browser — navigate in-place instead
            args.Handled = true;

            if (_viewModel.IsDomainAllowed(args.Uri))
            {
                BrowserWebView.Source = new UrlWebViewSource { Url = args.Uri };
            }
            else if (Uri.TryCreate(args.Uri, UriKind.Absolute, out var uri))
            {
                _viewModel.ErrorMessage = $"Navigation blocked: {uri.Host} is not in the allowed domains list.";
            }
        };
    }

    private void OnWebViewNavigating(object? sender, WebNavigatingEventArgs e)
    {
        // Don't filter when the browser panel isn't actively showing
        if (!_viewModel.ShowBrowser)
            return;

        // Allow non-HTTP navigations (about:blank, file:// app directory, etc.)
        if (!e.Url.StartsWith("http://", StringComparison.OrdinalIgnoreCase) &&
            !e.Url.StartsWith("https://", StringComparison.OrdinalIgnoreCase))
        {
            return;
        }

        // Filter navigation to allowed domains only
        if (!_viewModel.IsDomainAllowed(e.Url))
        {
            e.Cancel = true;
            if (Uri.TryCreate(e.Url, UriKind.Absolute, out var uri))
            {
                _viewModel.ErrorMessage = $"Navigation blocked: {uri.Host} is not in the allowed domains list.";
            }
        }
        else
        {
            _viewModel.ErrorMessage = string.Empty;
        }
    }

    private void OnWebViewNavigated(object? sender, WebNavigatedEventArgs e)
    {
        // Don't track when the browser panel isn't actively showing
        if (!_viewModel.ShowBrowser)
            return;

        // Only track HTTP(S) URLs as the current browsing URL
        if (e.Url.StartsWith("http://", StringComparison.OrdinalIgnoreCase) ||
            e.Url.StartsWith("https://", StringComparison.OrdinalIgnoreCase))
        {
            _viewModel.CurrentUrl = e.Url;
        }
    }
}
#endif
