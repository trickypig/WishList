using WishList.Share;
using Xunit;

public class SharedContentParserTests
{
    [Fact]
    public void Parse_DirectUrlWins()
    {
        var r = SharedContentParser.Parse("https://shop.example.com/p/1", "see https://other.com/x");
        Assert.Equal("https://shop.example.com/p/1", r.Url);
    }

    [Fact]
    public void Parse_AmazonAppText()
    {
        var r = SharedContentParser.Parse(null, "Cozy Wool Throw Blanket https://a.co/d/abc123");
        Assert.Equal("https://a.co/d/abc123", r.Url);
        Assert.Equal("Cozy Wool Throw Blanket", r.FallbackName);
    }

    [Fact]
    public void Parse_TextWithTrailingPunctuation()
    {
        var r = SharedContentParser.Parse(null, "Check out this deal: Cozy Throw https://a.co/d/abc123.");
        Assert.Equal("https://a.co/d/abc123", r.Url);
        Assert.Equal("Cozy Throw", r.FallbackName);
    }

    [Fact]
    public void Parse_KeepsColonInProductName()
    {
        var r = SharedContentParser.Parse(null, "Sony WH-1000XM5: Wireless Headphones https://a.co/d/x");
        Assert.Equal("Sony WH-1000XM5: Wireless Headphones", r.FallbackName);
    }

    [Fact]
    public void Parse_StripsStoreLabel()
    {
        var r = SharedContentParser.Parse(null, "Amazon.com: Cozy Throw https://a.co/d/x");
        Assert.Equal("Cozy Throw", r.FallbackName);
    }

    [Fact]
    public void Parse_NoLink()
    {
        var r = SharedContentParser.Parse(null, "just some words");
        Assert.Null(r.Url);
    }

    [Fact]
    public void Parse_IgnoresNonWebDirectUrl()
    {
        var r = SharedContentParser.Parse("file:///tmp/x", "Name https://shop.example.com/p");
        Assert.Equal("https://shop.example.com/p", r.Url);
    }

    [Fact]
    public void Parse_CapsLongName()
    {
        var r = SharedContentParser.Parse(null, new string('a', 500) + " https://x.com/p");
        Assert.Equal(200, r.FallbackName.Length);
    }
}
