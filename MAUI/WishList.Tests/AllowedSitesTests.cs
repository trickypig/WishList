using WishList.Helpers;
using WishList.Models;
using Xunit;

public class AllowedSitesTests
{
    static readonly IReadOnlySet<string> Allowed = AllowedSites.ToSet(["Amazon.com", "target.com", " "]);

    [Theory]
    [InlineData("https://amazon.com/dp/1")]
    [InlineData("https://www.amazon.com/dp/1")]
    [InlineData("http://smile.AMAZON.com/")]
    [InlineData("https://target.com./p/2")]
    public void Allowed_domains_and_their_subdomains_load(string url)
    {
        Assert.True(AllowedSites.IsAllowed(url, Allowed));
    }

    [Theory]
    [InlineData("https://notamazon.com/")]
    [InlineData("https://amazon.com.evil.net/")]
    [InlineData("https://google.com/?q=amazon.com")]
    [InlineData("ftp://amazon.com/")]
    [InlineData("intent://amazon.com#Intent;end")]
    [InlineData("javascript:alert(1)")]
    [InlineData("mailto:a@amazon.com")]
    [InlineData("")]
    [InlineData(null)]
    public void Everything_else_is_blocked(string? url)
    {
        Assert.False(AllowedSites.IsAllowed(url, Allowed));
    }

    [Fact]
    public void Host_drops_www()
    {
        Assert.Equal("amazon.com", AllowedSites.HostOf("https://www.amazon.com/dp/1"));
        Assert.Equal("shop.lego.com", AllowedSites.HostOf("https://shop.lego.com/"));
        Assert.Equal("", AllowedSites.HostOf("not a url"));
    }
}

public class ItemLinkLabelTests
{
    [Fact]
    public void Link_label_prefers_the_store_name()
    {
        Assert.Equal("Amazon", new ItemLink { Url = "https://www.amazon.com/x", StoreName = "Amazon" }.Label);
        Assert.Equal("amazon.com", new ItemLink { Url = "https://www.amazon.com/x", StoreName = " " }.Label);
    }

    [Fact]
    public void Only_web_links_can_open()
    {
        Assert.True(new ItemLink { Url = "https://target.com/p" }.CanOpen);
        Assert.False(new ItemLink { Url = "javascript:void(0)" }.CanOpen);
    }
}
