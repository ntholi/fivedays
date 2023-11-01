import {
  ActionIcon,
  Anchor,
  Breadcrumbs,
  Button,
  Container,
  Divider,
  Flex,
  Title,
} from '@mantine/core';
import googleClassroom from '@/lib/config/googleClassroom';
import { IconChevronLeft, IconWand } from '@tabler/icons-react';
import Link from 'next/link';
import CourseWorkForm from './CourseWorkForm';

type Props = {
  params: {
    courseId: string;
  };
};

async function getCourse(id: string) {
  const classroom = await googleClassroom();
  const { data: course } = await classroom.courses.get({ id });
  return course;
}

export default async function NewCourseWorkPage({ params }: Props) {
  const course = await getCourse(params.courseId);

  const items = [
    { title: 'Courses', href: '/courses' },
    { title: course.name, href: '.' },
  ].map((item, index) => (
    <Anchor component={Link} href={item.href} key={index}>
      {item.title}
    </Anchor>
  ));

  return (
    <Container mt='lg' size='xl'>
      <Breadcrumbs>{items}</Breadcrumbs>
      <Title mt='lg'>New Coursework</Title>
      <Flex mt='lg' justify={'space-between'} align='flex-end'>
        <Button
          variant='light'
          component={Link}
          href={'..'}
          leftSection={<IconChevronLeft />}
        >
          Back
        </Button>
      </Flex>
      <Divider my='md' />
      <CourseWorkForm course={course} />
    </Container>
  );
}
