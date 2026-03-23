using CommunityToolkit.Mvvm.Messaging;
using WishList.Models;
using WishList.ViewModels;

namespace WishList.Views;

public partial class ItemFormPage : ContentPage
{
    private readonly ItemFormViewModel _viewModel;

    public ItemFormPage(ItemFormViewModel viewModel)
    {
        InitializeComponent();
        BindingContext = _viewModel = viewModel;
    }

    protected override void OnAppearing()
    {
        base.OnAppearing();
#if WINDOWS
        // Register (or re-register) the capture handler each time the page appears,
        // since OnDisappearing unregisters it when navigating to BrowserPage.
        WeakReferenceMessenger.Default.Unregister<ItemCapturedMessage>(this);
        WeakReferenceMessenger.Default.Register<ItemCapturedMessage>(this, (r, m) =>
        {
            var result = m.Value;
            MainThread.BeginInvokeOnMainThread(() =>
            {
                if (!string.IsNullOrEmpty(result.Name))
                    _viewModel.Name = result.Name;
                if (result.Price.HasValue)
                    _viewModel.PriceText = result.Price.Value.ToString("F2");
                if (!string.IsNullOrEmpty(result.ImageUrl))
                    _viewModel.ImageUrl = result.ImageUrl;
                if (!string.IsNullOrEmpty(result.Url))
                {
                    _viewModel.Links.Add(new ItemLink
                    {
                        Url = result.Url,
                        StoreName = result.StoreName
                    });
                }
            });
        });
#endif
    }

    protected override void OnDisappearing()
    {
        base.OnDisappearing();
#if WINDOWS
        WeakReferenceMessenger.Default.Unregister<ItemCapturedMessage>(this);
#endif
    }

    private async void OnBrowseForItemClicked(object? sender, EventArgs e)
    {
#if WINDOWS
        await Shell.Current.GoToAsync($"browser?listId={_viewModel.ListId}");
#else
        await Task.CompletedTask;
#endif
    }
}
