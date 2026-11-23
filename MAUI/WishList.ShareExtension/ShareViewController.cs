using Foundation;
using ObjCRuntime;
using UIKit;
using WishList.Models;
using WishList.Platforms.iOS;
using WishList.Share;

namespace WishList.ShareExtension;

/// <summary>
/// Share → Wish List. Reads the shared page or link, loads the user's own lists and the page's
/// details side by side, and saves the item. Saving never waits on the details.
/// </summary>
[Register("ShareViewController")]
public sealed class ShareViewController : UIViewController
{
    const string LastListKey = "lastShareListId";
    const string NotLoggedIn = "Open Wish List and log in first";
    const string NoLists = "Create a list in Wish List first";
    const string NoLink = "No link found to save";

    readonly CancellationTokenSource _cts = new();
    ShareSheetView _sheet = null!;
    ShareDraft? _draft;
    ShareApiClient? _api;
    List<WishListModel> _lists = [];
    WishListModel? _selected;
    bool _saving, _done;

    public ShareViewController() { }
    public ShareViewController(NativeHandle handle) : base(handle) { }

    public override void LoadView()
    {
        _sheet = new ShareSheetView();
        _sheet.CancelTapped += Cancel;
        _sheet.SaveTapped += Save;
        _sheet.NameEdited += v => _draft?.EditName(v);
        _sheet.PriceEdited += v => _draft?.EditPrice(v);
        _sheet.NoteEdited += v => _draft?.EditNote(v);
        _sheet.ListSelected += list =>
        {
            _selected = list;
            _sheet.SetLists(_lists, _selected);
        };
        View = _sheet;
    }

    public override async void ViewDidLoad()
    {
        base.ViewDidLoad();
        try
        {
            await StartAsync();
        }
        catch (Exception ex) when (ex is not OperationCanceledException)
        {
            _sheet.SetStatus(ex.Message, isError: true);
        }
    }

    async Task StartAsync()
    {
        _sheet.SetFieldsEnabled(false);
        var input = await ShareInputReader.ReadAsync(ExtensionContext);
        var content = SharedContentParser.Parse(input.Url, input.Text);
        if (content.Url is null)
        {
            _sheet.SetStatus(NoLink);
            return;
        }

        _draft = new ShareDraft(content);
        _sheet.ShowDraft(_draft);

        var token = KeychainTokenStore.Get();
        if (string.IsNullOrEmpty(token))
        {
            ShowNotLoggedIn();
            return;
        }

        _api = new ShareApiClient(token);
        _sheet.SetFieldsEnabled(true);
        await Task.WhenAll(LoadListsAsync(), LoadDetailsAsync(content.Url, input.Html));
    }

    async Task LoadListsAsync()
    {
        try
        {
            _lists = await _api!.GetListsAsync(_cts.Token);
        }
        catch (NotLoggedInException)
        {
            ShowNotLoggedIn();
            return;
        }

        if (_lists.Count == 0)
        {
            _sheet.SetStatus(NoLists);
            return;
        }

        var stored = (int)NSUserDefaults.StandardUserDefaults.IntForKey(LastListKey);
        _selected = DefaultListPicker.Pick(_lists, stored == 0 ? null : stored);
        _sheet.SetLists(_lists, _selected);
        if (!_done) _sheet.SetSaveEnabled(true);
    }

    /// <summary>Best effort: the page HTML from Safari, else our server; failures leave the fallback name and link.</summary>
    async Task LoadDetailsAsync(string url, string? html)
    {
        _sheet.SetLoadingDetails(true);
        try
        {
            ScrapeResult? result = null;
            if (!string.IsNullOrEmpty(html))
                result = await TryAsync(() => _api!.ScrapeHtmlAsync(url, html, _cts.Token));
            result ??= await TryAsync(() => _api!.ScrapeUrlAsync(url, _cts.Token));
            if (result is null || _draft is null) return;

            _draft.Apply(result);
            _sheet.ShowDraft(_draft);
            await LoadImageAsync(_draft.ImageUrl);
        }
        finally
        {
            _sheet.SetLoadingDetails(false);
        }
    }

    async Task LoadImageAsync(string imageUrl)
    {
        if (string.IsNullOrWhiteSpace(imageUrl)) return;
        try
        {
            using var http = new HttpClient { Timeout = TimeSpan.FromSeconds(10) };
            var bytes = await http.GetByteArrayAsync(imageUrl, _cts.Token);
            if (UIImage.LoadFromData(NSData.FromArray(bytes)) is { } image)
                _sheet.SetImage(image);
        }
        catch (Exception ex) when (ex is HttpRequestException or TaskCanceledException or UriFormatException or InvalidOperationException)
        {
            // No picture is fine; the item still saves with its image URL.
        }
    }

    static async Task<T?> TryAsync<T>(Func<Task<T>> call) where T : class
    {
        try
        {
            return await call();
        }
        catch (Exception ex) when (ex is HttpRequestException or TaskCanceledException or NotLoggedInException or System.Text.Json.JsonException)
        {
            return null;
        }
    }

    async void Save()
    {
        if (_saving || _done || _selected is null || _draft is null || _api is null) return;

        // Disable first so a double tap can't create two items
        _saving = true;
        _sheet.SetSaveEnabled(false);
        _sheet.SetFieldsEnabled(false);
        _sheet.SetStatus(null);
        var list = _selected;

        try
        {
            await _api.CreateItemAsync(list.Id, _draft.ToItem(), _cts.Token);
            _done = true;
            NSUserDefaults.StandardUserDefaults.SetInt(list.Id, LastListKey);
            _sheet.SetStatus($"Saved to {list.Title}");
            await Task.Delay(1200);
            ExtensionContext?.CompleteRequest([], null);
        }
        catch (NotLoggedInException)
        {
            ShowNotLoggedIn();
        }
        catch (Exception ex) when (ex is not OperationCanceledException)
        {
            _sheet.SetStatus(ex.Message, isError: true);
            _sheet.SetFieldsEnabled(true);
            _sheet.SetSaveEnabled(true);
        }
        finally
        {
            _saving = false;
        }
    }

    void ShowNotLoggedIn()
    {
        _sheet.SetStatus(NotLoggedIn, isError: true);
        _sheet.SetSaveEnabled(false);
        _sheet.SetFieldsEnabled(false);
    }

    void Cancel()
    {
        _cts.Cancel();
        // NSUserCancelledError
        ExtensionContext?.CancelRequest(new NSError(NSError.CocoaErrorDomain, 3072));
    }
}
