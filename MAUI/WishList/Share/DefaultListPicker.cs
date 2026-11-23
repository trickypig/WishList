using WishList.Models;

namespace WishList.Share;

/// <summary>Which list the share sheet selects first: the one used last time, else the first list.</summary>
public static class DefaultListPicker
{
    public static WishListModel? Pick(IReadOnlyList<WishListModel> lists, int? lastListId) =>
        lists.FirstOrDefault(l => l.Id == lastListId) ?? lists.FirstOrDefault();
}
