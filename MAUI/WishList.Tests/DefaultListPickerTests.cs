using WishList.Models;
using WishList.Share;
using Xunit;

public class DefaultListPickerTests
{
    static List<WishListModel> Lists() => [new() { Id = 1 }, new() { Id = 2 }];

    [Fact]
    public void Pick_LastUsed() => Assert.Equal(2, DefaultListPicker.Pick(Lists(), 2)!.Id);

    [Fact]
    public void Pick_MissingLastFallsBackToFirst() => Assert.Equal(1, DefaultListPicker.Pick(Lists(), 99)!.Id);

    [Fact]
    public void Pick_NoLastUsesFirst() => Assert.Equal(1, DefaultListPicker.Pick(Lists(), null)!.Id);

    [Fact]
    public void Pick_NoLists() => Assert.Null(DefaultListPicker.Pick([], 1));
}
