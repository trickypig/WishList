namespace WishList.Helpers;

public static class SecureStorageHelper
{
#if IOS
    // On iOS the token lives in a keychain group shared with the share extension.
    // Tokens saved by older versions are in MAUI SecureStorage; move them over on first read
    // so nobody is logged out by the update.
    public static async Task<string?> GetTokenAsync()
    {
        var token = Platforms.iOS.KeychainTokenStore.Get();
        if (token != null)
            return token;

        var legacy = await SecureStorage.Default.GetAsync(Constants.TokenKey);
        if (string.IsNullOrEmpty(legacy))
            return legacy;

        Platforms.iOS.KeychainTokenStore.Set(legacy);
        SecureStorage.Default.Remove(Constants.TokenKey);
        return legacy;
    }

    public static Task SetTokenAsync(string token)
    {
        Platforms.iOS.KeychainTokenStore.Set(token);
        SecureStorage.Default.Remove(Constants.TokenKey);
        return Task.CompletedTask;
    }

    public static void RemoveToken()
    {
        Platforms.iOS.KeychainTokenStore.Remove();
        SecureStorage.Default.Remove(Constants.TokenKey);
    }
#else
    public static async Task<string?> GetTokenAsync()
    {
        return await SecureStorage.Default.GetAsync(Constants.TokenKey);
    }

    public static async Task SetTokenAsync(string token)
    {
        await SecureStorage.Default.SetAsync(Constants.TokenKey, token);
    }

    public static void RemoveToken()
    {
        SecureStorage.Default.Remove(Constants.TokenKey);
    }
#endif
}
