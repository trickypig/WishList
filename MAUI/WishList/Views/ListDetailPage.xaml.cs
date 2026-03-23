using WishList.ViewModels;

namespace WishList.Views;

public partial class ListDetailPage : ContentPage
{
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
}
