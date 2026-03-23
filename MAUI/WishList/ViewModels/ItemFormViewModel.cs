using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using WishList.Models;
using WishList.Services;

namespace WishList.ViewModels;

[QueryProperty(nameof(ListId), "listId")]
[QueryProperty(nameof(ItemId), "itemId")]
public partial class ItemFormViewModel : ObservableObject
{
    private readonly IApiService _apiService;

    [ObservableProperty]
    private int _listId;

    [ObservableProperty]
    private int _itemId;

    [ObservableProperty]
    private string _name = string.Empty;

    [ObservableProperty]
    private string _description = string.Empty;

    [ObservableProperty]
    private string _priceText = string.Empty;

    [ObservableProperty]
    private int _quantityDesired = 1;

    [ObservableProperty]
    private string _imageUrl = string.Empty;

    [ObservableProperty]
    private string _errorMessage = string.Empty;

    [ObservableProperty]
    private bool _isBusy;

    [ObservableProperty]
    private bool _isEditing;

    [ObservableProperty]
    private string _scrapeUrl = string.Empty;

    [ObservableProperty]
    private bool _isScraping;

    public ObservableCollection<ItemLink> Links { get; } = [];

    public string PageTitle => IsEditing ? "Edit Item" : "Add Item";

    public ItemFormViewModel(IApiService apiService)
    {
        _apiService = apiService;
    }

    partial void OnItemIdChanged(int value)
    {
        if (value > 0)
        {
            IsEditing = true;
            OnPropertyChanged(nameof(PageTitle));
            _ = LoadItemAsync();
        }
    }

    private async Task LoadItemAsync()
    {
        try
        {
            var list = await _apiService.GetListAsync(ListId);
            var item = list.Items?.FirstOrDefault(i => i.Id == ItemId);
            if (item == null) return;

            Name = item.Name;
            Description = item.Description;
            PriceText = item.Price?.ToString("F2") ?? "";
            QuantityDesired = item.QuantityDesired;
            ImageUrl = item.ImageUrl;
            Links.Clear();
            foreach (var link in item.Links)
                Links.Add(link);
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
    }

    [RelayCommand]
    private async Task SaveAsync()
    {
        if (string.IsNullOrWhiteSpace(Name))
        {
            ErrorMessage = "Name is required.";
            return;
        }

        IsBusy = true;
        ErrorMessage = string.Empty;

        try
        {
            decimal? price = null;
            if (decimal.TryParse(PriceText, out var p))
                price = p;

            var item = new Item
            {
                Name = Name,
                Description = Description,
                Price = price,
                QuantityDesired = QuantityDesired,
                ImageUrl = ImageUrl,
                Links = [.. Links]
            };

            if (IsEditing)
                await _apiService.UpdateItemAsync(ItemId, item);
            else
                await _apiService.CreateItemAsync(ListId, item);

            await Shell.Current.GoToAsync("..");
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
        finally
        {
            IsBusy = false;
        }
    }

    [RelayCommand]
    private async Task ScrapeAsync()
    {
        if (string.IsNullOrWhiteSpace(ScrapeUrl))
        {
            ErrorMessage = "Enter a URL to scrape.";
            return;
        }

        IsScraping = true;
        ErrorMessage = string.Empty;

        try
        {
            var result = await _apiService.ScrapeUrlAsync(ScrapeUrl);

            if (!string.IsNullOrEmpty(result.Name))
                Name = result.Name;
            if (result.Price.HasValue)
                PriceText = result.Price.Value.ToString("F2");
            if (!string.IsNullOrEmpty(result.ImageUrl))
                ImageUrl = result.ImageUrl;

            // Add as a link
            if (!string.IsNullOrEmpty(result.Url))
            {
                Links.Add(new ItemLink
                {
                    Url = result.Url,
                    StoreName = result.StoreName
                });
            }
        }
        catch (Exception ex)
        {
            ErrorMessage = ex.Message;
        }
        finally
        {
            IsScraping = false;
        }
    }

    [RelayCommand]
    private void AddLink()
    {
        Links.Add(new ItemLink());
    }

    [RelayCommand]
    private void RemoveLink(ItemLink link)
    {
        Links.Remove(link);
    }

    /// <summary>
    /// Called from the browser sidebar (Windows) when "Capture This Item" is pressed.
    /// </summary>
    public async Task CaptureFromBrowserAsync(string url)
    {
        ScrapeUrl = url;
        await ScrapeAsync();
    }
}
