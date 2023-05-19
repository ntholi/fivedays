import {
  Card,
  Image,
  Title,
  Text,
  createStyles,
  rem,
  Overlay,
  AspectRatio,
  Skeleton,
  Flex,
} from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import Link from 'next/link';
import React from 'react';

type Props = {
  item: classroom_v1.Schema$Course;
};

export default function ClassCard({ item }: Props) {
  const { classes } = useStyles();

  const imageId = String(item.id).charAt(0);

  return (
    <Link
      style={{ textDecoration: 'none', color: 'inherit' }}
      href={{ pathname: `/assessments/${item.id}`, query: { name: item.name } }}
    >
      <Card withBorder radius="md" mih={230} className={classes.card}>
        <Card.Section>
          <AspectRatio ratio={10 / 4} p={0}>
            <Overlay color="#000" opacity={0.5} />
            <Image src={`/images/${imageId}.jpg`} alt="" fit="cover" />
          </AspectRatio>
        </Card.Section>
        <Title order={4} fw={500} mt="md">
          {item.name}
        </Title>
      </Card>
    </Link>
  );
}

export function ClassCardSkeletons() {
  return (
    <>
      <Skeleton radius="md" mih={230} />
      <Skeleton radius="md" mih={230} />
      <Skeleton radius="md" mih={230} />
    </>
  );
}

const useStyles = createStyles((theme) => ({
  card: {
    // transition: 'box-shadow 0.3s ease, border-color 0.3s ease',
    // '&:hover': {
    //   boxShadow: `${rem(0)} ${rem(2)} ${rem(4)} ${
    //     theme.colorScheme === 'dark'
    //       ? theme.colors.dark[4]
    //       : theme.colors.gray[6]
    //   }`,
    //   cursor: 'pointer',
    // },
  },
}));
