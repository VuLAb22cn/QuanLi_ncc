/**
 * JavaScript cho ứng dụng quản lý nhà cung cấp
 */

$(document).ready(() => {
  // Khởi tạo tooltips
  $('[data-toggle="tooltip"]').tooltip()

  // Khởi tạo popovers
  $('[data-toggle="popover"]').popover()

  // Auto-hide alerts sau 5 giây
  setTimeout(() => {
    $(".alert").fadeOut("slow")
  }, 5000)

  // Format số tiền
  $(".currency-input").on("input", function () {
    const value = this.value.replace(/[^\d]/g, "")
    this.value = formatCurrency(value)
  })

  // Validate form
  $(".needs-validation").on("submit", function (e) {
    if (!this.checkValidity()) {
      e.preventDefault()
      e.stopPropagation()
    }
    $(this).addClass("was-validated")
  })

  // Search functionality
  $("#searchInput").on("keyup", function () {
    const value = $(this).val().toLowerCase()
    $("#dataTable tbody tr").filter(function () {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    })
  })

  // Phone number formatting
  $('input[name="tel"]').on("input", function () {
    this.value = this.value.replace(/[^0-9]/g, "")
  })

  // Email validation
  $('input[type="email"]').on("blur", function () {
    const email = $(this).val()
    if (email && !isValidEmail(email)) {
      $(this).addClass("is-invalid")
    } else {
      $(this).removeClass("is-invalid")
    }
  })

  // Image preview
  $('input[type="file"]').on("change", function () {
    const file = this.files[0]
    if (file) {
      const reader = new FileReader()
      reader.onload = function (e) {
        let preview = $(this).siblings(".image-preview")
        if (preview.length === 0) {
          preview = $('<div class="image-preview mt-2"></div>')
          $(this).after(preview)
        }
        preview.html('<img src="' + e.target.result + '" class="img-thumbnail" style="max-width: 200px;">')
      }.bind(this)
      reader.readAsDataURL(file)
    }
  })

  // Confirm delete
  $(".btn-danger").on("click", function (e) {
    if ($(this).attr("href") && $(this).attr("href").includes("delete")) {
      if (!confirm("Bạn có chắc chắn muốn xóa?")) {
        e.preventDefault()
      }
    }
  })

  // Auto-calculate total in forms
  $(".quantity-input, .price-input").on("input", () => {
    calculateTotal()
  })
})

/**
 * Format currency
 */
function formatCurrency(amount) {
  if (!amount) return ""
  return new Intl.NumberFormat("vi-VN").format(amount)
}

/**
 * Parse currency từ string
 */
function parseCurrency(str) {
  return Number.parseFloat(str.replace(/[^\d.-]/g, ""))
}

/**
 * Validate email
 */
function isValidEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(email)
}

/**
 * Show loading spinner
 */
function showLoading(element) {
  element.html('<i class="fas fa-spinner fa-spin"></i> Đang xử lý...')
  element.prop("disabled", true)
}

/**
 * Hide loading spinner
 */
function hideLoading(element, originalText) {
  element.html(originalText)
  element.prop("disabled", false)
}

/**
 * Format number with thousand separators
 */
function formatNumber(num) {
  return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".")
}

/**
 * Calculate total for bill items
 */
function calculateTotal() {
  let total = 0
  $(".bill-item").each(function () {
    const quantity = Number.parseFloat($(this).find(".quantity-input").val()) || 0
    const price = parseCurrency($(this).find(".price-input").val()) || 0
    const itemTotal = quantity * price
    $(this).find(".item-total").text(formatCurrency(itemTotal))
    total += itemTotal
  })
  $("#bill-total").text(formatCurrency(total))
}

/**
 * Add new bill item row
 */
function addBillItem() {
  const template = $("#bill-item-template").html()
  $("#bill-items").append(template)

  // Re-bind events for new row
  $(".quantity-input, .price-input")
    .off("input")
    .on("input", () => {
      calculateTotal()
    })
}

/**
 * Remove bill item row
 */
function removeBillItem(button) {
  $(button).closest(".bill-item").remove()
  calculateTotal()
}

/**
 * Load suppliers for select dropdown
 */
function loadSuppliers(selectElement, selectedId = null) {
  $.ajax({
    url: "ajax/get_suppliers.php",
    method: "GET",
    success: (response) => {
      try {
        const suppliers = JSON.parse(response)
        selectElement.empty()
        selectElement.append('<option value="">Chọn nhà cung cấp</option>')

        suppliers.forEach((supplier) => {
          const selected = selectedId == supplier.id ? "selected" : ""
          selectElement.append(`<option value="${supplier.id}" ${selected}>${supplier.name}</option>`)
        })
      } catch (e) {
        console.error("Error parsing suppliers data:", e)
      }
    },
    error: () => {
      console.error("Error loading suppliers")
    },
  })
}

/**
 * Load products by supplier
 */
function loadProductsBySupplier(supplierId, selectElement) {
  if (!supplierId) {
    selectElement.empty().append('<option value="">Chọn sản phẩm</option>')
    return
  }

  $.ajax({
    url: "ajax/get_products.php",
    method: "GET",
    data: { supplier_id: supplierId },
    success: (response) => {
      try {
        const products = JSON.parse(response)
        selectElement.empty()
        selectElement.append('<option value="">Chọn sản phẩm</option>')

        products.forEach((product) => {
          selectElement.append(`<option value="${product.id}" data-price="${product.price}">${product.name}</option>`)
        })
      } catch (e) {
        console.error("Error parsing products data:", e)
      }
    },
    error: () => {
      console.error("Error loading products")
    },
  })
}

/**
 * Export to Excel
 */
function exportToExcel(table, filename) {
  const tableHTML = $(table).prop("outerHTML")
  const blob = new Blob([tableHTML], {
    type: "application/vnd.ms-excel",
  })

  const link = document.createElement("a")
  link.href = URL.createObjectURL(blob)
  link.download = filename + ".xls"
  link.click()
}

/**
 * Print table
 */
function printTable(tableId) {
  const printContents = document.getElementById(tableId).outerHTML
  const originalContents = document.body.innerHTML

  document.body.innerHTML = `
        <html>
        <head>
            <title>In báo cáo</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                @media print {
                    .no-print { display: none !important; }
                    table { font-size: 12px; }
                }
            </style>
        </head>
        <body>
            <div class="container-fluid">
                <h2 class="text-center mb-4">BÁO CÁO QUẢN LÝ NHÀ CUNG CẤP</h2>
                ${printContents}
                <div class="text-center mt-4">
                    <p><em>Ngày in: ${new Date().toLocaleDateString("vi-VN")}</em></p>
                </div>
            </div>
        </body>
        </html>
    `

  window.print()
  document.body.innerHTML = originalContents
  location.reload()
}

/**
 * Send notification email
 */
function sendNotification(type, id) {
    $.ajax({
        url: 'ajax/send_notification.php',
        method: 'POST',
        data: {
            type: type,
            id: id
        },
        beforeSend: () => {
            $('#sendBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Đang gửi...');
        },
        success: (response) => {
            if (response ===
