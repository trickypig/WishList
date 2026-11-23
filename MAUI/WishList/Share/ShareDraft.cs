using System.Globalization;
using WishList.Helpers;
using WishList.Models;

namespace WishList.Share;

/// <summary>
/// The item being added from the share sheet. Page details can arrive after the user has
/// started typing, so anything the user edited is never overwritten.
/// </summary>
public sealed class ShareDraft
{
    bool _nameEdited, _priceEdited;

    public ShareDraft(SharedContent content)
    {
        Url = content.Url ?? string.Empty;
        Name = content.FallbackName;
    }

    public string Url { get; private set; }
    public string Name { get; private set; }
    public string PriceText { get; private set; } = string.Empty;
    public string Note { get; private set; } = string.Empty;
    public string ImageUrl { get; private set; } = string.Empty;
    public string StoreName { get; private set; } = string.Empty;

    public void EditName(string value) { Name = value; _nameEdited = true; }
    public void EditPrice(string value) { PriceText = value; _priceEdited = true; }
    public void EditNote(string value) => Note = value;

    public void Apply(ScrapeResult r)
    {
        if (!_nameEdited && !string.IsNullOrWhiteSpace(r.Name)) Name = r.Name.Trim();
        if (!_priceEdited && r.Price is decimal price) PriceText = price.ToString("0.00", CultureInfo.InvariantCulture);
        if (!string.IsNullOrWhiteSpace(r.ImageUrl)) ImageUrl = r.ImageUrl;
        if (!string.IsNullOrWhiteSpace(r.StoreName)) StoreName = r.StoreName;
        if (!string.IsNullOrWhiteSpace(r.Url)) Url = r.Url;
    }

    public Item ToItem() => new()
    {
        Name = string.IsNullOrWhiteSpace(Name) ? AllowedSites.HostOf(Url) : Name.Trim(),
        Price = ParsePrice(PriceText),
        Description = Note.Trim(),
        ImageUrl = ImageUrl,
        QuantityDesired = 1,
        Links = [new ItemLink { Url = Url, StoreName = StoreName }]
    };

    static decimal? ParsePrice(string text)
    {
        var digits = new string(text.Where(c => char.IsDigit(c) || c == '.').ToArray());
        return decimal.TryParse(digits, NumberStyles.Number, CultureInfo.InvariantCulture, out var value) ? value : null;
    }
}
