using WishList.ViewModels;

namespace WishList.Views;

public partial class FamiliesPage : ContentPage
{
    private readonly FamiliesViewModel _viewModel;

    public FamiliesPage(FamiliesViewModel viewModel)
    {
        InitializeComponent();
        BindingContext = _viewModel = viewModel;
    }

    protected override void OnAppearing()
    {
        base.OnAppearing();
        _viewModel.LoadFamiliesCommand.Execute(null);
    }
}
