namespace WishList.Helpers;

/// <summary>
/// Decides which web addresses the in-app browsers may load: only http(s) pages on a
/// family's allowed domains or their subdomains (www.amazon.com is allowed by amazon.com).
/// </summary>
public static class AllowedSites
{
    /// <summary>Lowercased domains, without empty entries, for quick lookups.</summary>
    public static HashSet<string> ToSet(IEnumerable<string> domains) =>
        domains.Select(d => d.Trim().TrimEnd('.').ToLowerInvariant())
               .Where(d => d.Length > 0)
               .ToHashSet();

    public static bool IsWebUrl(string? url) =>
        Uri.TryCreate(url, UriKind.Absolute, out var uri)
        && (uri.Scheme == Uri.UriSchemeHttp || uri.Scheme == Uri.UriSchemeHttps);

    public static bool IsAllowed(string? url, IReadOnlySet<string> allowed)
    {
        if (!IsWebUrl(url)) return false;

        var host = new Uri(url!).Host.TrimEnd('.').ToLowerInvariant();
        foreach (var domain in allowed)
        {
            if (host == domain || host.EndsWith("." + domain, StringComparison.Ordinal))
                return true;
        }
        return false;
    }

    /// <summary>The host without a leading "www.", for showing where a link goes.</summary>
    public static string HostOf(string? url)
    {
        if (!Uri.TryCreate(url, UriKind.Absolute, out var uri)) return string.Empty;
        var host = uri.Host;
        return host.StartsWith("www.", StringComparison.OrdinalIgnoreCase) ? host[4..] : host;
    }
}
