using CommunityToolkit.Maui;
using Microsoft.Extensions.Logging;
using WishList.Services;
using WishList.ViewModels;
using WishList.Views;

namespace WishList;

public static class MauiProgram
{
    public static MauiApp CreateMauiApp()
    {
        var builder = MauiApp.CreateBuilder();
        builder
            .UseMauiApp<App>()
            .UseMauiCommunityToolkit()
            .ConfigureFonts(fonts =>
            {
                fonts.AddFont("OpenSans-Regular.ttf", "OpenSansRegular");
                fonts.AddFont("OpenSans-Semibold.ttf", "OpenSansSemibold");
            });

        // Services (each creates its own HttpClient internally)
        builder.Services.AddSingleton<IAuthService, AuthService>();
        builder.Services.AddSingleton<IApiService, ApiService>();

        // ViewModels
        builder.Services.AddTransient<LoginViewModel>();
        builder.Services.AddTransient<RegisterViewModel>();
        builder.Services.AddTransient<DashboardViewModel>();
        builder.Services.AddTransient<ListDetailViewModel>();
        builder.Services.AddTransient<ItemFormViewModel>();
        builder.Services.AddTransient<FamiliesViewModel>();
        builder.Services.AddTransient<FamilyDetailViewModel>();
        builder.Services.AddTransient<FamilyListsViewModel>();
        builder.Services.AddTransient<SettingsViewModel>();

        // Pages
        builder.Services.AddTransient<LoginPage>();
        builder.Services.AddTransient<RegisterPage>();
        builder.Services.AddTransient<DashboardPage>();
        builder.Services.AddTransient<ListDetailPage>();
        builder.Services.AddTransient<ItemFormPage>();
        builder.Services.AddTransient<FamiliesPage>();
        builder.Services.AddTransient<FamilyDetailPage>();
        builder.Services.AddTransient<FamilyListsPage>();
        builder.Services.AddTransient<SettingsPage>();

#if WINDOWS
        builder.Services.AddTransient<BrowserViewModel>();
        builder.Services.AddTransient<BrowserPage>();
#endif

#if DEBUG
        builder.Logging.AddDebug();
#endif

        return builder.Build();
    }
}
