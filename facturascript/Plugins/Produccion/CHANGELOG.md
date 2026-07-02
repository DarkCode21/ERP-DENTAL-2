# Changelog

All significant changes to this project will be documented in this file.

## [2.0.5] - 2026-06-02
### Bug Fixes
- Fixed recipe printing to convert numeric values to strings and avoid incompatibility with the PlantillasPDF plugin.


## [2.0.4] - 2026-05-25
### New Features and Improvements
- Added serial number traceability report for produced products.
    - A traceability button is now available on each serial number row in the production order's serial numbers tab.
    - Clicking it opens a modal with full traceability details: product data, production order info, and the list of ingredients with their consumed serial numbers.
    - A print button allows exporting the traceability report as a PDF document.


## [2.0.3] - 2026-04-28

### New Features and Improvements
- Select whether to display cost columns when printing recipes. Now, from the Production settings panel, you can check or uncheck whether cost data should be shown when printing recipes.
- Select whether or not to allow creating recipes with the same code. Now, from the Production settings panel, you can check or uncheck whether creating recipes with the same code is permitted.
- Added manufacturing data (user, date, and time).
- Added filters to the production order list by user and manufacturing date.
- Added filter and sorting to the production order list by expiration date.

- New window with the product information sheet.
  - There is now a button in the list of ingredients and products produced/to be manufactured, in recipes and production orders, that opens a modal window with the information sheet of the selected product, including its image.

- Added the ability to specify the main product in the recipe.
  - The main product is selected from the list of produced products.
  - The main product is displayed in the recipe and production order listings.
  - Recipes and production orders can be searched and filtered by the main product.

- Added the ability to clone production orders from the order editing screen.
  - When cloning an order, the new order is in its initial state.
  - When cloning a production order, the ingredients and produced products are copied.
  - When cloning a production order, serial numbers are not copied.

### Changes
- The recipe identifier is now displayed in the lists.
- The product reference is now displayed in the serial number counter list and editing screen.
- The product reference is now displayed when assigning serial numbers on the customer delivery note.
- A column with the recipe code has been added to the production order list.
- Added a column with the recipe cost to the recipe list.
- When printing a customer delivery note with products that have serial numbers, the serial number assigned to each product is now displayed.
- When producing a recipe or a production order, production is now allowed even if the product is marked as "out of stock."

### Bug Fixes
- Corrected settings update process for compatibility with the 2025 Core Settings model.
- Corrected database structure update process to prevent duplicate production of products.
- Fixed an error when creating production orders when automatic confirmation of quantities to be prepared was not selected.
- Corrected quantity calculation in the production order list.
- Corrected variant cost calculation when more than one product is produced. The cost is now distributed among the quantity produced.
- When editing a customer delivery note, the serial numbers assigned to the products were not displayed correctly.
- Corrected pipe "addStock" so that it does not add the stock of all products when producing a recipe order, but only adds the stock of the indicated produced product.
- Corrected pipe "removeStock" so that it does not remove the stock of all products when producing a recipe order, but only removes the stock of the indicated ingredient.

## [2.0.1] - 2025-11-19

### New Features and Improvements
- Support for producing products with serial numbers.


## [2.0.0] - 2025-10-09

### Changes
- Plugin updated for compatibility with the 2025 core.


## [1.53.0] - 2025-04-14

## New Features and Improvements
- Added the option to specify whether decimal places are desired in production quantities.
- Added the "sold" column to recipe lines to indicate whether the product is a raw material or a finished product.
- Attachments can now be included in recipes.
- Replacement of references or products in recipes. References can now be automatically replaced (in a single recipe, a selection of recipes, or all recipes).
- Added the Ingredients and Produce tabs to the recipe and production order listing controller. These new tabs display the list of materials used and produced in production orders. They also allow filtering by different fields, making it easier to locate orders.
- Added a user field to record the user who performed the production. A filter has also been added.
- Search for production orders by order identifier.

## Bug Fixes
- Division by zero when producing recipes with a quantity of zero.
