using WishList.Models;
using Xunit;

public class WishListModelTests
{
    [Fact]
    public void List_color_matches_its_hero_and_the_website()
    {
        Assert.Equal("#ffb35c", new WishListModel { Id = 7 }.ListColorHex);
    }

    [Fact]
    public void HasDescription_ignores_blank()
    {
        Assert.False(new WishListModel { Description = "  " }.HasDescription);
        Assert.True(new WishListModel { Description = "Cozy stuff" }.HasDescription);
    }
}

public class WishListModelCountTests
{
    [Fact]
    public void HasItemCount_when_the_api_sent_one()
    {
        Assert.True(new WishListModel { ItemCount = 0 }.HasItemCount);
        Assert.False(new WishListModel { ItemCount = null }.HasItemCount);
    }
}

public class ItemCountTextTests
{
    [Fact]
    public void Item_count_text_is_singular_for_one()
    {
        Assert.Equal("1 item", new WishListModel { ItemCount = 1 }.ItemCountText);
        Assert.Equal("0 items", new WishListModel { ItemCount = 0 }.ItemCountText);
        Assert.Equal("6 items", new WishListModel { ItemCount = 6 }.ItemCountText);
    }
}
