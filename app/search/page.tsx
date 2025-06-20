"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Search, Building, FileText } from "lucide-react"

export default function SearchPage() {
  const [searchTerm, setSearchTerm] = useState("")
  const [searchType, setSearchType] = useState("all")
  const [dateRange, setDateRange] = useState("all")

  const searchResults = {
    suppliers: [
      {
        id: "SUP-001",
        name: "Công ty TNHH ABC",
        email: "contact@abc.com",
        phone: "0123456789",
        status: "active",
      },
      {
        id: "SUP-002",
        name: "Công ty XYZ",
        email: "info@xyz.com",
        phone: "0987654321",
        status: "active",
      },
    ],
    invoices: [
      {
        id: "INV-001",
        supplier: "Công ty TNHH ABC",
        amount: "500,000 VNĐ",
        status: "paid",
        date: "2024-01-15",
      },
      {
        id: "INV-002",
        supplier: "Công ty XYZ",
        amount: "750,000 VNĐ",
        status: "pending",
        date: "2024-01-14",
      },
    ],
    payments: [
      {
        id: "PAY-001",
        supplier: "Công ty TNHH ABC",
        amount: "500,000 VNĐ",
        status: "completed",
        date: "2024-01-15",
      },
    ],
    contracts: [
      {
        id: "CON-001",
        supplier: "Công ty TNHH ABC",
        title: "Hợp đồng vận chuyển",
        status: "active",
        startDate: "2024-01-01",
      },
    ],
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold">Tìm kiếm toàn diện</h1>
        <p className="text-muted-foreground">Tìm kiếm thông tin trong tất cả các module</p>
      </div>

      {/* Search Form */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Search className="h-5 w-5" />
            Tìm kiếm nâng cao
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4">
            <div className="flex space-x-4">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                <Input
                  placeholder="Nhập từ khóa tìm kiếm..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="pl-10"
                />
              </div>
              <Button>
                <Search className="mr-2 h-4 w-4" />
                Tìm kiếm
              </Button>
            </div>
            <div className="flex space-x-4">
              <Select value={searchType} onValueChange={setSearchType}>
                <SelectTrigger className="w-48">
                  <SelectValue placeholder="Loại tìm kiếm" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Tất cả</SelectItem>
                  <SelectItem value="suppliers">Nhà cung cấp</SelectItem>
                  <SelectItem value="invoices">Hóa đơn</SelectItem>
                  <SelectItem value="payments">Thanh toán</SelectItem>
                  <SelectItem value="contracts">Hợp đồng</SelectItem>
                </SelectContent>
              </Select>
              <Select value={dateRange} onValueChange={setDateRange}>
                <SelectTrigger className="w-48">
                  <SelectValue placeholder="Khoảng thời gian" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Tất cả</SelectItem>
                  <SelectItem value="today">Hôm nay</SelectItem>
                  <SelectItem value="week">Tuần này</SelectItem>
                  <SelectItem value="month">Tháng này</SelectItem>
                  <SelectItem value="year">Năm này</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Search Results */}
      <Tabs defaultValue="all" className="space-y-4">
        <TabsList>
          <TabsTrigger value="all">Tất cả</TabsTrigger>
          <TabsTrigger value="suppliers">Nhà cung cấp ({searchResults.suppliers.length})</TabsTrigger>
          <TabsTrigger value="invoices">Hóa đơn ({searchResults.invoices.length})</TabsTrigger>
          <TabsTrigger value="payments">Thanh toán ({searchResults.payments.length})</TabsTrigger>
          <TabsTrigger value="contracts">Hợp đồng ({searchResults.contracts.length})</TabsTrigger>
        </TabsList>

        <TabsContent value="all" className="space-y-4">
          {/* Suppliers Results */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Building className="h-5 w-5" />
                Nhà cung cấp ({searchResults.suppliers.length})
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {searchResults.suppliers.map((supplier) => (
                  <div key={supplier.id} className="flex items-center justify-between p-3 border rounded-lg">
                    <div>
                      <h4 className="font-medium">{supplier.name}</h4>
                      <p className="text-sm text-muted-foreground">
                        {supplier.email} • {supplier.phone}
                      </p>
                    </div>
                    <Badge variant={supplier.status === "active" ? "default" : "secondary"}>
                      {supplier.status === "active" ? "Hoạt động" : "Không hoạt động"}
                    </Badge>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>

          {/* Invoices Results */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <FileText className="h-5 w-5" />
                Hóa đơn ({searchResults.invoices.length})
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {searchResults.invoices.map((invoice) => (
                  <div key={invoice.id} className="flex items-center justify-between p-3 border rounded-lg">
                    <div>
                      <h4 className="font-medium">{invoice.id}</h4>
                      <p className="text-sm text-muted-foreground">
                        {invoice.supplier} • {invoice.date}
                      </p>
                    </div>
                    <div className="text-right">
                      <p className="font-medium">{invoice.amount}</p>
                      <Badge variant={invoice.status === "paid" ? "default" : "secondary"}>
                        {invoice.status === "paid" ? "Đã thanh toán" : "Chờ thanh toán"}
                      </Badge>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="suppliers">
          <Card>
            <CardHeader>
              <CardTitle>Kết quả tìm kiếm nhà cung cấp</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {searchResults.suppliers.map((supplier) => (
                  <div
                    key={supplier.id}
                    className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50"
                  >
                    <div>
                      <h4 className="font-medium">{supplier.name}</h4>
                      <p className="text-sm text-muted-foreground">Mã: {supplier.id}</p>
                      <p className="text-sm text-muted-foreground">
                        {supplier.email} • {supplier.phone}
                      </p>
                    </div>
                    <div className="flex items-center space-x-2">
                      <Badge variant={supplier.status === "active" ? "default" : "secondary"}>
                        {supplier.status === "active" ? "Hoạt động" : "Không hoạt động"}
                      </Badge>
                      <Button variant="outline" size="sm">
                        Xem chi tiết
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="invoices">
          <Card>
            <CardHeader>
              <CardTitle>Kết quả tìm kiếm hóa đơn</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {searchResults.invoices.map((invoice) => (
                  <div
                    key={invoice.id}
                    className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50"
                  >
                    <div>
                      <h4 className="font-medium">{invoice.id}</h4>
                      <p className="text-sm text-muted-foreground">{invoice.supplier}</p>
                      <p className="text-sm text-muted-foreground">Ngày: {invoice.date}</p>
                    </div>
                    <div className="text-right">
                      <p className="font-medium text-lg">{invoice.amount}</p>
                      <Badge variant={invoice.status === "paid" ? "default" : "secondary"}>
                        {invoice.status === "paid" ? "Đã thanh toán" : "Chờ thanh toán"}
                      </Badge>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="payments">
          <Card>
            <CardHeader>
              <CardTitle>Kết quả tìm kiếm thanh toán</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {searchResults.payments.map((payment) => (
                  <div
                    key={payment.id}
                    className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50"
                  >
                    <div>
                      <h4 className="font-medium">{payment.id}</h4>
                      <p className="text-sm text-muted-foreground">{payment.supplier}</p>
                      <p className="text-sm text-muted-foreground">Ngày: {payment.date}</p>
                    </div>
                    <div className="text-right">
                      <p className="font-medium text-lg">{payment.amount}</p>
                      <Badge className="bg-green-100 text-green-800">Hoàn thành</Badge>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="contracts">
          <Card>
            <CardHeader>
              <CardTitle>Kết quả tìm kiếm hợp đồng</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {searchResults.contracts.map((contract) => (
                  <div
                    key={contract.id}
                    className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50"
                  >
                    <div>
                      <h4 className="font-medium">{contract.title}</h4>
                      <p className="text-sm text-muted-foreground">Mã: {contract.id}</p>
                      <p className="text-sm text-muted-foreground">{contract.supplier}</p>
                      <p className="text-sm text-muted-foreground">Bắt đầu: {contract.startDate}</p>
                    </div>
                    <div className="flex items-center space-x-2">
                      <Badge variant="default">Đang hoạt động</Badge>
                      <Button variant="outline" size="sm">
                        Xem chi tiết
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  )
}
