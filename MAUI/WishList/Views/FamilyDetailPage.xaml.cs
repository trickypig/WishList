using WishList.ViewModels;

namespace WishList.Views;

public partial class FamilyDetailPage : ContentPage
{
    public FamilyDetailPage(FamilyDetailViewModel viewModel)
    {
        InitializeComponent();
        BindingContext = viewModel;
    }
}
