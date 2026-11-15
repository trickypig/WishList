using WishList.Helpers;
using WishList.Models;
using Xunit;

public class BoldPopTests
{
    [Fact]
    public void ListColor_matches_website_palette_by_list_id()
    {
        Assert.Equal("#7cd4ff", BoldPop.ListColorHex(0));
        Assert.Equal("#ffb35c", BoldPop.ListColorHex(7));
    }

    [Fact]
    public void Tile_color_wraps_after_five()
    {
        Assert.Equal("#7cd4ff", BoldPop.TileHexAt(5));
    }

    [Fact]
    public void CountOpen_counts_items_not_units()
    {
        Assert.Equal((0, 0), BoldPop.CountOpen([]));
        var items = new[]
        {
            new Item { QuantityDesired = 3, TotalPurchased = 1 },
            new Item { QuantityDesired = 1, TotalPurchased = 1 },
            new Item { QuantityDesired = 2, TotalPurchased = null },
        };
        Assert.Equal((2, 3), BoldPop.CountOpen(items));
    }

    [Fact]
    public void DropAfter_uses_horizontal_half()
    {
        Assert.False(BoldPop.DropAfter(30, 100));
        Assert.True(BoldPop.DropAfter(70, 100));
    }

    [Fact]
    public void ColumnsFor_never_drops_below_two()
    {
        Assert.Equal(2, BoldPop.ColumnsFor(358, 200, 12));
        Assert.Equal(5, BoldPop.ColumnsFor(1100, 200, 12));
        Assert.Equal(2, BoldPop.ColumnsFor(100, 200, 12));
    }

    [Fact]
    public void TileWidth_splits_space_after_gaps()
    {
        Assert.Equal(173, BoldPop.TileWidth(358, 2, 12));
    }
}

public class RoleColorTests
{
    [Fact]
    public void Role_colors_follow_the_spec()
    {
        Assert.Equal("#ffe14d", BoldPop.RoleHex("admin"));
        Assert.Equal("#c9a7ff", BoldPop.RoleHex("child"));
        Assert.Equal("#7cd4ff", BoldPop.RoleHex("member"));
        Assert.Equal("#7cd4ff", BoldPop.RoleHex(null));
    }
}
