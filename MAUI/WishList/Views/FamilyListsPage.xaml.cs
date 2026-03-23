using WishList.ViewModels;

namespace WishList.Views;

public partial class FamilyListsPage : ContentPage
{
    public FamilyListsPage(FamilyListsViewModel viewModel)
    {
        InitializeComponent();
        BindingContext = viewModel;
    }
}
