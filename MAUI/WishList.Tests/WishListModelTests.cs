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

public class ListSectionTests
{
    [Fact]
    public void Own_lists_can_be_deleted_and_hide_the_owner()
    {
        var list = new WishListModel { OwnerName = "Sam" };
        new ListSection("My Lists", "", true, [list]);
        Assert.True(list.IsMine);
        Assert.False(list.ShowOwnerName);
    }

    [Fact]
    public void Family_lists_show_who_they_belong_to()
    {
        var list = new WishListModel { OwnerName = "Grandma" };
        var section = new ListSection("The Smiths", "Lists shared with this family", false, [list]);
        Assert.False(list.IsMine);
        Assert.True(list.ShowOwnerName);
        Assert.False(section.IsEmptySection);
    }

    [Fact]
    public void Empty_sections_say_why()
    {
        Assert.Contains("New List", new ListSection("My Lists", "", true, []).EmptyText);
        Assert.Contains("shared", new ListSection("Fam", "", false, []).EmptyText);
    }
}
