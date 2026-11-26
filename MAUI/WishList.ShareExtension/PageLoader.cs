using CoreGraphics;
using Foundation;
using UIKit;
using WebKit;

namespace WishList.ShareExtension;

/// <summary>
/// Loads a shared link in an invisible web view on the phone and returns the page's HTML.
/// Only Safari runs GetPage.js. Chrome and most other apps share just the link, and many stores
/// block our server when it fetches the page, but they serve it to the user's own phone.
/// </summary>
public static class PageLoader
{
    // Script-rendered stores fill in the price and images after the page "finishes" loading
    static readonly TimeSpan SettleDelay = TimeSpan.FromSeconds(1.5);

    /// <summary>The final URL (after redirects) and HTML, or null if the page didn't load.</summary>
    public static async Task<(string Url, string Html)?> LoadAsync(UIView host, string url, TimeSpan timeout, CancellationToken ct)
    {
        if (NSUrl.FromString(url) is not { } nsUrl) return null;

        // true = finished, false = failed; a timeout keeps whatever has loaded so far
        var loaded = new TaskCompletionSource<bool>(TaskCreationOptions.RunContinuationsAsynchronously);
        var navigation = new NavigationDelegate(loaded);
        // In the view hierarchy but invisible: a detached web view can have its scripts throttled
        var webView = new WKWebView(new CGRect(0, 0, 1, 1), new WKWebViewConfiguration())
        {
            Alpha = 0,
            UserInteractionEnabled = false,
            NavigationDelegate = navigation
        };
        host.AddSubview(webView);

        try
        {
            webView.LoadRequest(new NSUrlRequest(nsUrl));

            var finished = await Task.WhenAny(loaded.Task, Task.Delay(timeout, ct)) == loaded.Task;
            ct.ThrowIfCancellationRequested();
            if (finished && !loaded.Task.Result) return null;
            if (finished) await Task.Delay(SettleDelay, ct);

            var html = (await webView.EvaluateJavaScriptAsync("document.documentElement.outerHTML"))?.ToString();
            if (string.IsNullOrEmpty(html)) return null;
            return (webView.Url?.AbsoluteString ?? url, html);
        }
        catch (NSErrorException)
        {
            // The page wouldn't give up its HTML; the caller falls back to our server
            return null;
        }
        finally
        {
            webView.StopLoading();
            webView.NavigationDelegate = null!;
            webView.RemoveFromSuperview();
            GC.KeepAlive(navigation);
        }
    }

    sealed class NavigationDelegate(TaskCompletionSource<bool> loaded) : WKNavigationDelegate
    {
        public override void DidFinishNavigation(WKWebView webView, WKNavigation navigation) =>
            loaded.TrySetResult(true);

        public override void DidFailNavigation(WKWebView webView, WKNavigation navigation, NSError error) =>
            loaded.TrySetResult(false);

        public override void DidFailProvisionalNavigation(WKWebView webView, WKNavigation navigation, NSError error) =>
            loaded.TrySetResult(false);
    }
}
