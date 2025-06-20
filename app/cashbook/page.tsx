"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Plus, Search, TrendingUp, TrendingDown } from "lucide-react"

interface CashbookEntry {
  id: string
  date: string
  type: "income" | "expense"
  amount: string
  description: string
  category: string
  reference: string
}

export default function CashbookPage() {
  const [searchTerm, setSearchTerm] = useState("")
  const [typeFilter, setTypeFilter] = useState("all")
  const [isDialogOpen, setIsDialogOpen] = useState(false)

  const entries: CashbookEntry[] = [
    {
      id: "CB-001",
      date: "2024-01-15",
      type: "expense",
      amount: "500,000 VNĐ",
      description: "Thanh toán hóa đơn vận chuyển",
      category: "Logistics",
      reference: "INV-001",
    },
    {
      id: "CB-002",
      date: "2024-01-14",
      type: "income",
      amount: "2,000,000 VNĐ",
      description: "Thu tiền từ khách hàng",
      category: "Sales",
      reference: "SALE-001",
    },
    {
      id: "CB-003",
      date: "2024-01-13",
      type: "expense",
      amount: "750,000 VNĐ",
      description: "Chi phí bảo trì thiết bị",
      category: "Maintenance",
      reference: "INV-002",
    },
    {
      id: "CB-004",
      date: "2024-01-12",
      type: "expense",
      amount: "300,000 VNĐ",
      description: "Chi phí văn phòng phẩm",
      category: "Office",
      reference: "PUR-001",
    },
  ]

  const filteredEntries = entries.filter((entry) => {
    const matchesSearch =
      entry.description.toLowerCase().includes(searchTerm.toLowerCase()) ||
      entry.reference.toLowerCase().includes(searchTerm.toLowerCase()) ||
      entry.id.toLowerCase().includes(searchTerm.toLowerCase())
    const matchesType = typeFilter === "all" || entry.type === typeFilter
    return matchesSearch && matchesType
  })

  // Calculate totals
  const totalIncome = entries
    .filter((entry) => entry.type === "income")
    .reduce((sum, entry) => sum + Number.parseFloat(entry.amount.replace(/[^\d]/g, "")), 0)

  const totalExpense = entries
    .filter((entry) => entry.type === "expense")
    .reduce((sum, entry) => sum + Number.parseFloat(entry.amount.replace(/[^\d]/g, "")), 0)

  const balance = totalIncome - totalExpense

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold">Sổ quỹ</h1>
          <p className="text-muted-foreground">Quản lý thu chi và dòng tiền</p>
        </div>
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button>
              <Plus className="mr-2 h-4 w-4" />
              Thêm giao dịch
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-2xl">
            <DialogHeader>
              <DialogTitle>Thêm giao dịch mới</DialogTitle>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="type">Loại giao dịch</Label>
                  <Select>
                    <SelectTrigger>
                      <SelectValue placeholder="Chọn loại" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="income">Thu</SelectItem>
                      <SelectItem value="expense">Chi</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="amount">Số tiền</Label>
                  <Input id="amount" placeholder="Nhập số tiền" />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="date">Ngày giao dịch</Label>
                  <Input id="date" type="date" />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="category">Danh mục</Label>
                  <Select>
                    <SelectTrigger>
                      <SelectValue placeholder="Chọn danh mục" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="logistics">Logistics</SelectItem>
                      <SelectItem value="maintenance">Bảo trì</SelectItem>
                      <SelectItem value="office">Văn phòng</SelectItem>
                      <SelectItem value="sales">Bán hàng</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="reference">Mã tham chiếu</Label>
                <Input id="reference" placeholder="Mã hóa đơn, đơn hàng..." />
              </div>
              <div className="space-y-2">
                <Label htmlFor="description">Mô tả</Label>
                <Textarea id="description" placeholder="Mô tả chi tiết giao dịch" />
              </div>
              <div className="flex justify-end space-x-2">
                <Button variant="outline" onClick={() => setIsDialogOpen(false)}>
                  Hủy
                </Button>
                <Button onClick={() => setIsDialogOpen(false)}>Lưu</Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      </div>

      {/* Summary Cards */}
      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Tổng thu</CardTitle>
            <TrendingUp className="h-4 w-4 text-green-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-green-600">{totalIncome.toLocaleString()} VNĐ</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Tổng chi</CardTitle>
            <TrendingDown className="h-4 w-4 text-red-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-red-600">{totalExpense.toLocaleString()} VNĐ</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Số dư</CardTitle>
          </CardHeader>
          <CardContent>
            <div className={`text-2xl font-bold ${balance >= 0 ? "text-green-600" : "text-red-600"}`}>
              {balance.toLocaleString()} VNĐ
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Lịch sử giao dịch</CardTitle>
          <div className="flex space-x-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
              <Input
                placeholder="Tìm kiếm theo mô tả, mã tham chiếu..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-10"
              />
            </div>
            <Select value={typeFilter} onValueChange={setTypeFilter}>
              <SelectTrigger className="w-48">
                <SelectValue placeholder="Lọc theo loại" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Tất cả</SelectItem>
                <SelectItem value="income">Thu</SelectItem>
                <SelectItem value="expense">Chi</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Mã GD</TableHead>
                <TableHead>Ngày</TableHead>
                <TableHead>Loại</TableHead>
                <TableHead>Số tiền</TableHead>
                <TableHead>Danh mục</TableHead>
                <TableHead>Mô tả</TableHead>
                <TableHead>Tham chiếu</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredEntries.map((entry) => (
                <TableRow key={entry.id}>
                  <TableCell className="font-medium">{entry.id}</TableCell>
                  <TableCell>{entry.date}</TableCell>
                  <TableCell>
                    <Badge variant={entry.type === "income" ? "default" : "destructive"}>
                      {entry.type === "income" ? "Thu" : "Chi"}
                    </Badge>
                  </TableCell>
                  <TableCell className={`font-medium ${entry.type === "income" ? "text-green-600" : "text-red-600"}`}>
                    {entry.type === "income" ? "+" : "-"}
                    {entry.amount}
                  </TableCell>
                  <TableCell>
                    <Badge variant="outline">{entry.category}</Badge>
                  </TableCell>
                  <TableCell>{entry.description}</TableCell>
                  <TableCell>{entry.reference}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}
