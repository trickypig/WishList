using System.Text.RegularExpressions;
using WishList.Helpers;

namespace WishList.Share;

/// <summary>What another app shared: the product link, and a name to fall back on if the page can't be read.</summary>
public sealed record SharedContent(string? Url, string FallbackName);

/// <summary>
/// Turns what the share sheet receives (a link, text with a link in it, or both) into a link and a name.
/// Shopping apps often share text like "Cozy Throw https://a.co/d/abc123".
/// </summary>
public static partial class SharedContentParser
{
    const int MaxNameLength = 200;
    const string EdgeChars = " \t\r\n-|:–";

    [GeneratedRegex(@"https?://[^\s<>""']+", RegexOptions.IgnoreCase)]
    private static partial Regex UrlPattern();

    [GeneratedRegex(@"^\s*(check out this (item|deal|product)\s*:?|check out|shop)\s*", RegexOptions.IgnoreCase)]
    private static partial Regex KnownPrefix();

    // A store label like "Amazon.com: " (not any text before a colon: product names contain colons too)
    [GeneratedRegex(@"^[a-z0-9-]+\.[a-z.]{2,10}\s*:\s+", RegexOptions.IgnoreCase)]
    private static partial Regex ShortLabelPrefix();

    public static SharedContent Parse(string? url, string? text)
    {
        text ??= string.Empty;
        string? foundUrl = null;
        var match = UrlPattern().Match(text);
        while (match.Success && foundUrl == null)
        {
            var candidate = match.Value.TrimEnd('.', ',', ';', ':', '!', '?', ')', ']', '}', '\'', '"');
            if (AllowedSites.IsWebUrl(candidate))
                foundUrl = candidate;
            else
                match = match.NextMatch();
        }

        var name = match.Success ? text.Remove(match.Index, match.Length) : text;
        name = KnownPrefix().Replace(name, string.Empty, 1);
        name = ShortLabelPrefix().Replace(name, string.Empty, 1);
        name = Regex.Replace(name, @"\s+", " ").Trim(EdgeChars.ToCharArray());
        if (name.Length > MaxNameLength)
            name = name[..MaxNameLength].TrimEnd();

        var chosenUrl = AllowedSites.IsWebUrl(url) ? url!.Trim() : foundUrl;
        return new SharedContent(chosenUrl, name);
    }
}
