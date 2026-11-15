using WishList.Helpers;

namespace WishList.Controls;

/// <summary>
/// Keeps a CollectionView's vertical grid at as many columns as fit (never fewer than two),
/// re-evaluated whenever its width changes.
/// </summary>
public static class GridSpan
{
    public static readonly BindableProperty MinItemWidthProperty =
        BindableProperty.CreateAttached("MinItemWidth", typeof(double), typeof(GridSpan), 0.0,
            propertyChanged: (b, _, n) =>
            {
                if (b is not CollectionView cv || (double)n <= 0) return;
                cv.ItemsLayout = new GridItemsLayout(2, ItemsLayoutOrientation.Vertical);
                cv.SizeChanged -= OnSizeChanged;
                cv.SizeChanged += OnSizeChanged;
            });

    public static double GetMinItemWidth(BindableObject view) => (double)view.GetValue(MinItemWidthProperty);

    public static void SetMinItemWidth(BindableObject view, double value) => view.SetValue(MinItemWidthProperty, value);

    static void OnSizeChanged(object? sender, EventArgs e)
    {
        if (sender is not CollectionView { Width: > 0 } cv || cv.ItemsLayout is not GridItemsLayout grid) return;
        var span = BoldPop.ColumnsFor(cv.Width - 32, GetMinItemWidth(cv), 0);
        if (grid.Span != span) grid.Span = span;
    }
}
