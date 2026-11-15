using WishList.Models;

namespace WishList.Helpers;

/// <summary>
/// Bold Pop display rules shared with the website (frontend/src/lib/listDisplay.ts).
/// Plain C# with no MAUI types, so the test project can compile it directly.
/// </summary>
public static class BoldPop
{
    public static readonly string[] TileHex = ["#7cd4ff", "#b7f171", "#ffb35c", "#c9a7ff", "#ff5c8a"];

    /// <summary>Stable per-list color; same list, same color as on the website.</summary>
    public static string ListColorHex(int listId) => TileHex[listId % TileHex.Length];

    /// <summary>Image tile color, rotating by position.</summary>
    public static string TileHexAt(int index) => TileHex[index % TileHex.Length];

    /// <summary>Items (not units) that still have quantity left to buy.</summary>
    public static (int Open, int Total) CountOpen(IEnumerable<Item> items)
    {
        var list = items.ToList();
        return (list.Count(i => (i.TotalPurchased ?? 0) < i.QuantityDesired), list.Count);
    }

    /// <summary>Role badge color: admin yellow, child lilac, everyone else sky.</summary>
    public static string RoleHex(string? role) => role?.ToLowerInvariant() switch
    {
        "admin" => "#ffe14d",
        "child" => "#c9a7ff",
        _ => TileHex[0],
    };

    /// <summary>Tiles sit in a grid: the right half of a tile means "drop after it".</summary>
    public static bool DropAfter(double x, double width) => x > width / 2;

    /// <summary>How many tile columns fit, never fewer than two.</summary>
    public static int ColumnsFor(double available, double minTile, double gap) =>
        Math.Max(2, (int)Math.Floor((available + gap) / (minTile + gap)));

    public static double TileWidth(double available, int columns, double gap) =>
        (available - gap * (columns - 1)) / columns;
}
