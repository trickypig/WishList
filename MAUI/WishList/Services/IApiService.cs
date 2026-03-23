using WishList.Models;

namespace WishList.Services;

public interface IApiService
{
    // Lists
    Task<List<WishListModel>> GetListsAsync();
    Task<WishListModel> CreateListAsync(string title, string description, string visibility);
    Task<WishListModel> GetListAsync(int id);
    Task<WishListModel> UpdateListAsync(int id, string title, string description);
    Task DeleteListAsync(int id);
    Task<WishListModel> UpdateListSharingAsync(int id, string visibility, List<int> familyIds);
    Task<List<WishListModel>> GetFamilyListsAsync(int familyId);

    // Items
    Task<Item> CreateItemAsync(int listId, Item item);
    Task<Item> UpdateItemAsync(int id, Item item);
    Task DeleteItemAsync(int id);
    Task ReorderItemsAsync(int listId, List<int> itemIds);

    // Purchases
    Task<Purchase> PurchaseItemAsync(int itemId, int quantity);
    Task UnpurchaseItemAsync(int itemId);

    // Families
    Task<List<Family>> GetFamiliesAsync();
    Task<Family> CreateFamilyAsync(string name);
    Task<Family> GetFamilyAsync(int id);
    Task<Family> JoinFamilyAsync(string inviteCode);
    Task InviteToFamilyAsync(int familyId, string email);
    Task RemoveFamilyMemberAsync(int familyId, int userId);
    Task<User> SetChildStatusAsync(int familyId, int userId, int isChild);

    // Domains
    Task<List<AllowedDomain>> GetFamilyDomainsAsync(int familyId);
    Task AddFamilyDomainAsync(int familyId, string domain, string displayName);
    Task RemoveFamilyDomainAsync(int familyId, string domain);

    // Scraping
    Task<ScrapeResult> ScrapeUrlAsync(string url);
}
