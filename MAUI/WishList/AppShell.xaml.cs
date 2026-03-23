using WishList.Views;

namespace WishList;

public partial class AppShell : Shell
{
    public AppShell()
    {
        InitializeComponent();

        // Register detail routes
        Routing.RegisterRoute("listDetail", typeof(ListDetailPage));
        Routing.RegisterRoute("itemForm", typeof(ItemFormPage));
        Routing.RegisterRoute("familyDetail", typeof(FamilyDetailPage));
        Routing.RegisterRoute("familyLists", typeof(FamilyListsPage));

#if WINDOWS
        Routing.RegisterRoute("browser", typeof(BrowserPage));
#endif
    }
}
