namespace WishList.Models;

/// <summary>A heading on the Lists tab and the lists under it: your own, or one family's.</summary>
public class ListSection : List<WishListModel>
{
    public ListSection(string title, string subtitle, bool isMine, IEnumerable<WishListModel> lists) : base(lists)
    {
        Title = title;
        Subtitle = subtitle;
        IsMine = isMine;
        foreach (var list in this)
            list.IsMine = isMine;
    }

    public string Title { get; }

    public string Subtitle { get; }

    public bool IsMine { get; }

    public bool IsEmptySection => Count == 0;

    public string EmptyText => IsMine
        ? "No wish lists yet. Tap '+ New List' to create one!"
        : "Nobody in this family has shared a list yet.";
}
