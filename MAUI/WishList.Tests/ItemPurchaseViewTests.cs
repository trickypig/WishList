using WishList.Models;
using Xunit;

public class ItemPurchaseViewTests
{
    static Item Make(int qty, int? bought, int? mine, bool familyView) => new()
    {
        QuantityDesired = qty, TotalPurchased = bought, PurchasedByMe = mine, ShowPurchaseState = familyView,
    };

    [Fact]
    public void Owner_never_sees_purchase_state()
    {
        var item = Make(1, 1, 0, familyView: false);
        Assert.False(item.ShowStripes);
        Assert.False(item.IsClaimedByOthers);
        Assert.False(item.ShowMyBadge);
        Assert.False(item.ShowProgress);
    }

    [Fact]
    public void Fully_bought_by_others_is_claimed_and_striped()
    {
        var item = Make(1, 1, 0, familyView: true);
        Assert.True(item.IsClaimedByOthers);
        Assert.True(item.ShowStripes);
    }

    [Fact]
    public void Partly_bought_by_others_still_looks_available()
    {
        var item = Make(3, 1, 0, familyView: true);
        Assert.False(item.ShowStripes);
        Assert.True(item.ShowProgress);
        Assert.Equal("1 of 3 taken", item.TakenText);
    }

    [Fact]
    public void Bought_by_me_shows_badge()
    {
        Assert.True(Make(1, 1, 1, familyView: true).ShowMyBadge);
    }

    [Fact]
    public void Tile_color_follows_position()
    {
        Assert.Equal("#b7f171", new Item { TileIndex = 6 }.TileHex);
    }

    [Fact]
    public void Broken_image_falls_back()
    {
        var item = new Item { ImageUrl = "https://x/y.jpg" };
        Assert.True(item.HasImage);
        item.ImageFailed = true;
        Assert.False(item.HasImage);
        Assert.False(new Item { ImageUrl = "" }.HasImage);
        Assert.False(new Item { ImageUrl = "/giftbox.svg" }.HasImage);
    }
}

public class ItemTileTests
{
    [Fact]
    public void HasPrice_only_for_positive_prices()
    {
        Assert.True(new Item { Price = 12.5m }.HasPrice);
        Assert.False(new Item { Price = null }.HasPrice);
        Assert.False(new Item { Price = 0m }.HasPrice);
    }

    [Fact]
    public void Setting_TileIndex_refreshes_TileHex()
    {
        var item = new Item();
        var changed = new List<string?>();
        item.PropertyChanged += (_, e) => changed.Add(e.PropertyName);
        item.TileIndex = 2;
        Assert.Contains(nameof(Item.TileHex), changed);
    }
}

public class OwnerQuantityTests
{
    [Fact]
    public void Owner_sees_quantity_when_more_than_one()
    {
        var item = new Item { QuantityDesired = 3, ShowPurchaseState = false };
        Assert.True(item.ShowOwnerQuantity);
        Assert.Equal("Wants 3", item.OwnerQuantityText);
    }

    [Fact]
    public void Owner_quantity_hidden_for_single_items_and_for_family()
    {
        Assert.False(new Item { QuantityDesired = 1, ShowPurchaseState = false }.ShowOwnerQuantity);
        Assert.False(new Item { QuantityDesired = 3, ShowPurchaseState = true }.ShowOwnerQuantity);
    }
}
