namespace WishList.Helpers;

public static class Constants
{
#if DEBUG
    public const string ApiBaseUrl = "http://localhost:8080/api";
#else
    public const string ApiBaseUrl = "https://wish.trickypig.com/api";
#endif

    public const string TokenKey = "auth_token";
}
