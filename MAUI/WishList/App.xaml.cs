using WishList.Services;

namespace WishList;

public partial class App : Application
{
    private readonly IAuthService _authService;

    public App(IAuthService authService)
    {
        InitializeComponent();
        _authService = authService;
#if DEBUG
        // Dev aid: WISH_THEME=dark|light forces a theme so both modes can be checked
        // without changing the OS setting. Release builds always follow the device.
        var theme = Environment.GetEnvironmentVariable("WISH_THEME");
        if (string.Equals(theme, "dark", StringComparison.OrdinalIgnoreCase)) UserAppTheme = AppTheme.Dark;
        else if (string.Equals(theme, "light", StringComparison.OrdinalIgnoreCase)) UserAppTheme = AppTheme.Light;
#endif
    }

    protected override Window CreateWindow(IActivationState? activationState)
    {
        var shell = new AppShell();
        var window = new Window(shell);

        shell.Loaded += async (s, e) =>
        {
#if DEBUG
            // Dev aid for checking screens against a local sandbox API:
            // WISH_DEV_EMAIL / WISH_DEV_PASSWORD sign in, WISH_DEV_ROUTE opens a page.
            var devEmail = Environment.GetEnvironmentVariable("WISH_DEV_EMAIL");
            var devPassword = Environment.GetEnvironmentVariable("WISH_DEV_PASSWORD");
            if (!string.IsNullOrEmpty(devEmail) && !string.IsNullOrEmpty(devPassword))
            {
                await _authService.LoginAsync(devEmail, devPassword);
                await shell.GoToAsync("//main/dashboard");
                var devRoute = Environment.GetEnvironmentVariable("WISH_DEV_ROUTE");
                if (!string.IsNullOrEmpty(devRoute))
                    await shell.GoToAsync(devRoute);
                return;
            }
#endif
            var restored = await _authService.TryRestoreSessionAsync();
            if (restored)
            {
                await shell.GoToAsync("//main/dashboard");
            }
            else
            {
                await shell.GoToAsync("//login");
            }
        };

        return window;
    }
}
