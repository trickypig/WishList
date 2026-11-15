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
