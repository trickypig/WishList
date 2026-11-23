using Foundation;

namespace WishList.ShareExtension;

/// <summary>
/// Reads what the host app shared. Safari hands over the page (URL + HTML from GetPage.js);
/// most other apps hand over a URL, text, or both (the Amazon app shares "name + a.co link" text).
/// </summary>
public static class ShareInputReader
{
    const string PropertyList = "com.apple.property-list";
    const string WebUrl = "public.url";
    const string PlainText = "public.plain-text";
    // Value of NSExtensionJavaScriptPreprocessingResultsKey
    const string PreprocessingResultsKey = "NSExtensionJavaScriptPreprocessingResultsKey";

    public static async Task<(string? Url, string? Html, string? Text)> ReadAsync(NSExtensionContext? context)
    {
        string? url = null, html = null, text = null;

        foreach (var item in context?.InputItems ?? [])
        {
            foreach (var provider in item.Attachments ?? [])
            {
                if (html == null && provider.HasItemConformingTo(PropertyList))
                {
                    if (await LoadAsync(provider, PropertyList) is NSDictionary dict
                        && dict[PreprocessingResultsKey] is NSDictionary results)
                    {
                        url ??= results["url"]?.ToString();
                        html = results["html"]?.ToString();
                    }
                }

                if (url == null && provider.HasItemConformingTo(WebUrl))
                {
                    var value = await LoadAsync(provider, WebUrl);
                    url = (value as NSUrl)?.AbsoluteString ?? (value as NSString)?.ToString();
                }

                if (text == null && provider.HasItemConformingTo(PlainText))
                {
                    var value = await LoadAsync(provider, PlainText);
                    text = (value as NSString)?.ToString() ?? (value as NSAttributedString)?.Value;
                }
            }

            text ??= item.AttributedContentText?.Value;
        }

        return (url, html, text);
    }

    static async Task<NSObject?> LoadAsync(NSItemProvider provider, string type)
    {
        try
        {
            return await provider.LoadItemAsync(type, null);
        }
        catch (NSErrorException)
        {
            // An attachment that can't be read is skipped; the others may still have the link.
            return null;
        }
    }
}
