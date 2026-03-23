namespace WishList.Helpers;

public static class SecureStorageHelper
{
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
}
