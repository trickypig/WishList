using WishList.Models;
using WishList.ViewModels;

namespace WishList.Views;

public partial class ListDetailPage : ContentPage
{
    // Key the dragged Item travels under, so drops of anything else (files, links) are ignored
    private const string DraggedItemKey = "WishList.Item";

    public ListDetailPage(ListDetailViewModel viewModel)
    {
        InitializeComponent();
        BindingContext = viewModel;
    }

    protected override void OnAppearing()
    {
        base.OnAppearing();
        if (BindingContext is ListDetailViewModel vm && vm.ListId > 0)
            vm.LoadListCommand.Execute(null);
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
        var color = on ? (Color)Application.Current!.Resources["Primary"] : Colors.Transparent;
        switch (view)
        {
            case Frame frame:
                frame.BorderColor = color;
                break;
            case Border border:
                border.Stroke = on ? color : (Color)Application.Current!.Resources["Gray300"];
                break;
            case Layout layout:
                layout.BackgroundColor = on ? color.WithAlpha(0.12f) : Colors.Transparent;
                break;
        }
    }

    private void OnItemDragStarting(object? sender, DragStartingEventArgs e)
    {
        if (ViewOf(sender!)?.BindingContext is Item item)
            e.Data.Properties[DraggedItemKey] = item;
        else
            e.Cancel = true;
    }

    private void OnItemDragOver(object? sender, DragEventArgs e)
    {
        var accept = DraggedItem(e.Data.Properties) != null;
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

        // Lower half of a card means "drop after it"
        var y = e.GetPosition(view)?.Y ?? 0;
        await vm.DropOnItemAsync(dragged, target, after: y > view.Height / 2);
    }

    private void OnSectionDragOver(object? sender, DragEventArgs e)
    {
        var accept = DraggedItem(e.Data.Properties) != null;
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
