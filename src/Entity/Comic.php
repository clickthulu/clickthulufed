<?php

namespace App\Entity;

use App\Enumerations\NavigationTypeEnumeration;
use App\Repository\ComicRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ComicRepository::class)]
class Comic
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\OneToMany(mappedBy: 'comic', targetEntity: Chapter::class)]
    private Collection $chapters;

    #[ORM\OneToMany(mappedBy: 'comic', targetEntity: Page::class, orphanRemoval: true)]
    private Collection $pages;

    #[ORM\OneToOne(mappedBy: 'comic', cascade: ['persist', 'remove'])]
    private ?Layout $layout = null;

    #[ORM\Column]
    private ?bool $isactive = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column]
    private ?bool $isdeleted = false;

    #[ORM\OneToOne(mappedBy: 'comic', cascade: ['persist', 'remove'])]
    private ?Schedule $schedule = null;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $activatedon = null;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedon = null;

    #[ORM\ManyToOne(inversedBy: 'comics')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $Owner = null;

    #[ORM\OneToMany(mappedBy: 'Comic', targetEntity: Cast::class, orphanRemoval: true)]
    private Collection $casts;

    private ?Page $activePage;

    #[ORM\Column(length: 255)]
    private ?string $navigationtype = NavigationTypeEnumeration::NAV_DATE;

    #[ORM\ManyToMany(targetEntity: Tag::class, mappedBy: 'comic')]
    private Collection $tags;

    private ?Page $firstpage;
    private ?Page $latestpage;
    private ?Page $currentpage;


    public function __construct()
    {
        $this->admin = new ArrayCollection();
        $this->chapters = new ArrayCollection();
        $this->pages = new ArrayCollection();
        $this->casts = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, Chapter>
     */
    public function getChapters(): Collection
    {
        return $this->chapters;
    }

    public function addChapter(Chapter $chapter): static
    {
        if (!$this->chapters->contains($chapter)) {
            $this->chapters->add($chapter);
            $chapter->setComic($this);
        }

        return $this;
    }

    public function removeChapter(Chapter $chapter): static
    {
        if ($this->chapters->removeElement($chapter)) {
            // set the owning side to null (unless already changed)
            if ($chapter->getComic() === $this) {
                $chapter->setComic(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Page>
     */
    public function getPages(): Collection
    {
        return $this->pages;
    }

    public function addPage(Page $page): static
    {
        if (!$this->pages->contains($page)) {
            $this->pages->add($page);
            $page->setComic($this);
        }

        return $this;
    }

    public function removePage(Page $page): static
    {
        if ($this->pages->removeElement($page)) {
            // set the owning side to null (unless already changed)
            if ($page->getComic() === $this) {
                $page->setComic(null);
            }
        }

        return $this;
    }

    public function getLayout(): ?Layout
    {
        return $this->layout;
    }

    public function setLayout(Layout $layout): static
    {
        // set the owning side of the relation if necessary
        if ($layout->getComic() !== $this) {
            $layout->setComic($this);
        }

        $this->layout = $layout;

        return $this;
    }

    public function isIsactive(): ?bool
    {
        return $this->isactive;
    }

    public function setIsactive(bool $isactive): static
    {
        $this->isactive = $isactive;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function isIsdeleted(): ?bool
    {
        return $this->isdeleted;
    }

    public function setIsdeleted(bool $isdeleted): static
    {
        $this->isdeleted = $isdeleted;

        return $this;
    }

    public function getSchedule(): ?Schedule
    {
        return $this->schedule;
    }

    public function setSchedule(?Schedule $schedule): static
    {
        // unset the owning side of the relation if necessary
        if ($schedule === null && $this->schedule !== null) {
            $this->schedule->setComic(null);
        }

        // set the owning side of the relation if necessary
        if ($schedule !== null && $schedule->getComic() !== $this) {
            $schedule->setComic($this);
        }

        $this->schedule = $schedule;

        return $this;
    }

    public function getActivatedon(): ?\DateTimeInterface
    {
        return $this->activatedon;
    }

    public function setActivatedon(?\DateTimeInterface $activatedon): static
    {
        $this->activatedon = $activatedon;

        return $this;
    }

    public function getDeletedon(): ?\DateTimeInterface
    {
        return $this->deletedon;
    }

    public function setDeletedon(?\DateTimeInterface $deletedon): static
    {
        $this->deletedon = $deletedon;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->Owner;
    }

    public function setOwner(?User $Owner): static
    {
        $this->Owner = $Owner;

        return $this;
    }

    /**
     * @return Collection<int, Cast>
     */
    public function getCasts(): Collection
    {
        return $this->casts;
    }

    public function addCast(Cast $cast): static
    {
        if (!$this->casts->contains($cast)) {
            $this->casts->add($cast);
            $cast->setComic($this);
        }

        return $this;
    }

    public function removeCast(Cast $cast): static
    {
        if ($this->casts->removeElement($cast)) {
            // set the owning side to null (unless already changed)
            if ($cast->getComic() === $this) {
                $cast->setComic(null);
            }
        }

        return $this;
    }


    /**
     * @return ?Page
     */
    public function getActivePage(): ?Page
    {
        return $this->activePage;
    }

    public function setActivePage(?Page $page)
    {
        $this->activePage = $page;
        return $this;
    }

    public function getNavigationtype(): ?string
    {
        return $this->navigationtype;
    }

    public function setNavigationtype(string $navigationtype): static
    {
        $this->navigationtype = $navigationtype;

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
            $tag->addComic($this);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        if ($this->tags->removeElement($tag)) {
            $tag->removeComic($this);
        }

        return $this;
    }

    public function setPages(ArrayCollection $pages): self
    {
        $this->pages = $pages;
        return $this;
    }

    /**
     * @return Page|null
     */
    public function getFirstpage(): ?Page
    {
        return $this->firstpage;
    }

    /**
     * @param Page|null $firstpage
     */
    public function setFirstpage(?Page $firstpage): Comic
    {
        $this->firstpage = $firstpage;
        return $this;
    }

    /**
     * @return Page|null
     */
    public function getLatestpage(): ?Page
    {
        return $this->latestpage;
    }

    /**
     * @param Page|null $latestpage
     */
    public function setLatestpage(?Page $latestpage): Comic
    {
        $this->latestpage = $latestpage;
        return $this;
    }

    /**
     * @return Page|null
     */
    public function getCurrentpage(): ?Page
    {
        return $this->currentpage;
    }

    /**
     * @param Page|null $currentpage
     */
    public function setCurrentpage(?Page $currentpage): Comic
    {
        $this->currentpage = $currentpage;
        return $this;
    }

    public function pagesTillToday()
    {
        $now = new \DateTime('now');

        /**
         * @var Page $page
         */
        foreach ($this->pages as $page) {
            if ($page->getPublishdate() > $now) {
                $this->pages->removeElement($page);
            }
        }

        $iterator = $this->pages->getIterator();
        /**
         * @var Page $a
         * @var Page $b
         */
        $iterator->uasort(function($a, $b){
            return $a->getPublishDate() > $b->getPublishDate() ? -1 : 1;
        });

        $this->setPages(new ArrayCollection(iterator_to_array($iterator)));
        $this->currentpage = $this->pages->last();

        return $this;
    }
}
