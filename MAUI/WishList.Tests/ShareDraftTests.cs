using WishList.Models;
using WishList.Share;
using Xunit;

public class ShareDraftTests
{
    static ShareDraft Draft() => new(new SharedContent("https://a.co/d/x", "Cozy Throw"));

    static ScrapeResult Scraped() => new()
    {
        Url = "https://www.amazon.com/dp/B0X",
        Name = "Cozy Wool Throw",
        Price = 48m,
        ImageUrl = "https://img/x.jpg",
        StoreName = "Amazon"
    };

    [Fact]
    public void Apply_FillsFromScrape()
    {
        var d = Draft();
        d.Apply(Scraped());
        Assert.Equal("Cozy Wool Throw", d.Name);
        Assert.Equal("48.00", d.PriceText);
        Assert.Equal("https://www.amazon.com/dp/B0X", d.Url);
        Assert.Equal("Amazon", d.StoreName);
    }

    [Fact]
    public void Apply_DoesNotOverwriteEditedName()
    {
        var d = Draft();
        d.EditName("My blanket");
        d.Apply(Scraped());
        Assert.Equal("My blanket", d.Name);
        Assert.Equal("48.00", d.PriceText);
    }

    [Fact]
    public void Apply_DoesNotOverwriteEditedPrice()
    {
        var d = Draft();
        d.EditPrice("40");
        d.Apply(Scraped());
        Assert.Equal("40", d.PriceText);
    }

    [Fact]
    public void Apply_EmptyScrapeKeepsFallback()
    {
        var d = Draft();
        d.Apply(new ScrapeResult());
        Assert.Equal("Cozy Throw", d.Name);
        Assert.Equal("https://a.co/d/x", d.Url);
    }

    [Theory]
    [InlineData("$1,299.99", 1299.99)]
    [InlineData(" 48 ", 48.0)]
    [InlineData("abc", null)]
    [InlineData("", null)]
    public void ToItem_ParsesPrice(string text, double? expected)
    {
        var d = Draft();
        d.EditPrice(text);
        Assert.Equal(expected is null ? null : (decimal?)expected, d.ToItem().Price);
    }

    [Fact]
    public void ToItem_EmptyNameUsesHost()
    {
        var d = Draft();
        d.EditName("  ");
        Assert.Equal("a.co", d.ToItem().Name);
    }

    [Fact]
    public void ToItem_MapsNoteAndLink()
    {
        var d = Draft();
        d.Apply(Scraped());
        d.EditNote(" size M ");
        var item = d.ToItem();
        Assert.Equal("size M", item.Description);
        Assert.Equal(1, item.QuantityDesired);
        Assert.Equal("https://img/x.jpg", item.ImageUrl);
        var link = Assert.Single(item.Links);
        Assert.Equal("https://www.amazon.com/dp/B0X", link.Url);
        Assert.Equal("Amazon", link.StoreName);
    }
}
