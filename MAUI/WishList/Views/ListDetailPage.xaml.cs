using WishList.Controls;
using WishList.Helpers;
using WishList.Models;
using WishList.ViewModels;

namespace WishList.Views;

public partial class ListDetailPage : ContentPage
{
    // Key the dragged Item travels under, so drops of anything else (files, links) are ignored
    private const string DraggedItemKey = "WishList.Item";

    const double Gap = 12;
    const double MinTile = 200;
    const double MinGroupTile = 160;

    public ListDetailPage(ListDetailViewModel viewModel)
    {
        InitializeComponent();
        BindingContext = viewModel;
    }

    /// <summary>Pick a column count for this grid's width (never fewer than two) and size every slot to fill it.</summary>
    private void OnTileGridSizeChanged(object? sender, EventArgs e)
    {
        if (sender is FlexLayout grid)
            foreach (var child in grid.Children.OfType<View>())
                child.WidthRequest = SlotWidth(grid);
    }

    private void OnTileGridChildAdded(object? sender, ElementEventArgs e)
    {
        if (sender is FlexLayout { Width: > 0 } grid && e.Element is View child)
            child.WidthRequest = SlotWidth(grid);
    }

    /// <summary>A slot is a tile plus its gap (the slot's right/bottom padding).</summary>
    private static double SlotWidth(FlexLayout grid)
    {
        var minTile = grid.BindingContext is ItemSection { IsGroup: true } ? MinGroupTile : MinTile;
        var cols = BoldPop.ColumnsFor(grid.Width, minTile + Gap, 0);
        // cols * slot <= width, with a pixel spare for rounding
        return Math.Floor(grid.Width / cols) - 1;
    }

    /// <summary>
    /// A photo that can't load falls back to the plain tile with a gift. MAUI downloads
    /// web images itself and raises no failure event, so: loading finished, nothing to show.
    /// </summary>
    private void OnTileImagePropertyChanged(object? sender, System.ComponentModel.PropertyChangedEventArgs e)
    {
        if (e.PropertyName != nameof(Image.IsLoading) || sender is not Image { IsLoading: false } image)
            return;
        if (image.BindingContext is not Item { HasImage: true } item)
            return;
#if WINDOWS
        if (image.Handler?.PlatformView is Microsoft.UI.Xaml.Controls.Image { Source: null })
            item.ImageFailed = true;
#elif IOS || MACCATALYST
        if (image.Handler?.PlatformView is UIKit.UIImageView { Image: null })
            item.ImageFailed = true;
#endif
    }

    protected override void OnAppearing()
    {
        base.OnAppearing();
        if (BindingContext is ListDetailViewModel vm && vm.ListId > 0)
            vm.LoadListCommand.Execute(null);
    }

    protected override void OnDisappearing()
    {
        ViewModel?.ResetSuggestions();
        base.OnDisappearing();
    }

    private ListDetailViewModel? ViewModel => BindingContext as ListDetailViewModel;

    /// <summary>Gesture events can come from the recognizer or its view; either way we want the view.</summary>
    private static View? ViewOf(object sender) =>
        sender as View ?? (sender as GestureRecognizer)?.Parent as View;

    private static Item? DraggedItem(DataPackagePropertySetView properties) =>
        properties.TryGetValue(DraggedItemKey, out var value) ? value as Item : null;

    private static Item? DraggedItem(DataPackagePropertySet properties) =>
        properties.TryGetValue(DraggedItemKey, out var value) ? value as Item : null;

    private static void SetHighlight(View? view, bool on)
    {
        var color = on ? (Color)Application.Current!.Resources["Accent"] : Colors.Transparent;
        switch (view)
        {
            case PopCard card:
                card.OutlineColor = on ? color : null;
                break;
            case Frame frame:
                frame.BorderColor = color;
                break;
            case Border border:
                if (on) border.Stroke = color;
                else border.SetAppTheme<Brush>(Border.StrokeProperty,
                    new SolidColorBrush((Color)Application.Current!.Resources["InkLight"]),
                    new SolidColorBrush((Color)Application.Current!.Resources["InkDark"]));
                break;
            case Layout layout:
                layout.BackgroundColor = on ? color.WithAlpha(0.12f) : Colors.Transparent;
                break;
        }
    }

    private void OnItemDragStarting(object? sender, DragStartingEventArgs e)
    {
        if (ViewOf(sender!)?.BindingContext is Item { CanReorder: true } item)
            e.Data.Properties[DraggedItemKey] = item;
        else
            e.Cancel = true;
    }

    private void OnItemDragOver(object? sender, DragEventArgs e)
    {
        var accept = DraggedItem(e.Data.Properties) != null && ViewOf(sender!)?.BindingContext is Item { IsSuggestion: false };
        e.AcceptedOperation = accept ? DataPackageOperation.Copy : DataPackageOperation.None;
        SetHighlight(ViewOf(sender!), accept);
    }

    private void OnItemDragLeave(object? sender, DragEventArgs e) => SetHighlight(ViewOf(sender!), false);

    private async void OnItemDrop(object? sender, DropEventArgs e)
    {
        var view = ViewOf(sender!);
        SetHighlight(view, false);
        if (ViewModel is not { } vm || view?.BindingContext is not Item target || DraggedItem(e.Data.Properties) is not { } dragged)
            return;

        // Tiles sit in a grid: the right half of a tile means "drop after it"
        var x = e.GetPosition(view)?.X ?? 0;
        await vm.DropOnItemAsync(dragged, target, after: BoldPop.DropAfter(x, view.Width));
    }

    private void OnSectionDragOver(object? sender, DragEventArgs e)
    {
        var accept = DraggedItem(e.Data.Properties) != null && ViewOf(sender!)?.BindingContext is ItemSection { IsSuggestions: false };
        e.AcceptedOperation = accept ? DataPackageOperation.Copy : DataPackageOperation.None;
        SetHighlight(ViewOf(sender!), accept);
    }

    private void OnSectionDragLeave(object? sender, DragEventArgs e) => SetHighlight(ViewOf(sender!), false);

    private async void OnSectionDrop(object? sender, DropEventArgs e)
    {
        var view = ViewOf(sender!);
        SetHighlight(view, false);
        if (ViewModel is not { } vm || view?.BindingContext is not ItemSection section || DraggedItem(e.Data.Properties) is not { } dragged)
            return;

        await vm.DropOnSectionAsync(dragged, section);
    }
}
